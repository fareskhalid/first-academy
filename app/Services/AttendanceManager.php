<?php

namespace App\Services;

use App\Contracts\CourseAccess;
use App\Models\Attendance;
use App\Models\AttendanceAuthorization;
use App\Models\AttendanceDenial;
use App\Models\AttendanceIntent;
use App\Models\AttendanceRevision;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\GroupMembership;
use App\Models\QrCredential;
use App\Models\SessionRosterEntry;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceManager
{
    public function __construct(private readonly CourseAccess $access) {}

    public function createIntent(QrCredential $credential, User $student, string $browserSessionHash): AttendanceIntent
    {
        $denial = null;
        try {
            return DB::transaction(function () use ($credential, $student, $browserSessionHash, &$denial) {
                $credential = QrCredential::with('session.offering')->lockForUpdate()->findOrFail($credential->id);
                $session = $credential->session;
                $now = now();
                if ($credential->revoked_at || ! $now->lt($credential->expires_at) || $credential->revision !== $session->qr_revision
                    || ! in_array($session->status, ['published', 'in_progress'], true)
                    || $now->lt($session->attendance_opens_at) || ! $now->lt($session->attendance_closes_at)) {
                    throw ValidationException::withMessages(['attendance' => __('ui.qr_expired')]);
                }
                $enrollment = Enrollment::where('student_id', $student->id)->where('course_offering_id', $session->course_offering_id)
                    ->where('status', 'enrolled')->lockForUpdate()->first();
                if (! $enrollment || $session->offering->status !== 'open') {
                    $denial = [$session, $student, $enrollment, 'not_enrolled'];
                    throw ValidationException::withMessages(['attendance' => __('ui.attendance_not_enrolled')]);
                }
                if (! $this->eligibleForGroup($session, $enrollment)) {
                    $denial = [$session, $student, $enrollment, 'wrong_group'];
                    throw ValidationException::withMessages(['attendance' => __('ui.wrong_group')]);
                }

                $this->captureRoster($session);
                $expiresAt = $now->copy()->addMinutes((int) config('academic.attendance_intent_minutes', 2))
                    ->min($session->attendance_closes_at->copy()->addMinutes(2));

                return AttendanceIntent::create([
                    'id' => (string) Str::uuid(), 'class_session_id' => $session->id,
                    'enrollment_id' => $enrollment->id, 'user_id' => $student->id,
                    'qr_credential_id' => $credential->id, 'browser_session_hash' => $browserSessionHash,
                    'accepted_at' => $now, 'expires_at' => $expiresAt,
                ]);
            });
        } catch (ValidationException $exception) {
            if ($denial) {
                $this->deny(...$denial);
            }
            throw $exception;
        }
    }

    public function record(AttendanceIntent $intent, User $student, string $browserSessionHash): array
    {
        $denial = null;
        try {
            return DB::transaction(function () use ($intent, $student, $browserSessionHash, &$denial) {
                $intent = AttendanceIntent::with(['session.offering', 'session.lesson'])->lockForUpdate()->findOrFail($intent->id);
                abort_unless($intent->user_id === $student->id && hash_equals($intent->browser_session_hash, $browserSessionHash), 403);
                $enrollment = Enrollment::lockForUpdate()->findOrFail($intent->enrollment_id);

                $canonical = Attendance::where('enrollment_id', $enrollment->id)->where('lesson_id', $intent->session->lesson_id)->lockForUpdate()->first();
                if ($canonical && in_array($canonical->status, ['present', 'late'], true)) {
                    if (! $intent->consumed_at) {
                        $intent->update(['consumed_at' => now()]);
                    }

                    return $this->result($canonical, $enrollment, true);
                }

                $now = now();
                if ($intent->revoked_at || ! $now->lt($intent->expires_at) || $intent->consumed_at
                    || $intent->session->status === 'cancelled') {
                    throw ValidationException::withMessages(['attendance' => __('ui.intent_expired')]);
                }
                if ($student->status !== 'active' || $enrollment->status !== 'enrolled' || $intent->session->offering->status !== 'open') {
                    $denial = [$intent->session, $student, $enrollment, 'not_enrolled'];
                    throw ValidationException::withMessages(['attendance' => __('ui.attendance_not_enrolled')]);
                }
                if (! $this->eligibleForGroup($intent->session, $enrollment)) {
                    $denial = [$intent->session, $student, $enrollment, 'wrong_group'];
                    throw ValidationException::withMessages(['attendance' => __('ui.wrong_group')]);
                }

                $accessSource = $this->access->source($enrollment, $now);
                if ($accessSource === null) {
                    $denial = [$intent->session, $student, $enrollment, 'payment_required'];
                    throw ValidationException::withMessages(['attendance' => __('ui.payment_required')]);
                }

                $status = $intent->accepted_at->gte($intent->session->late_after_at) ? 'late' : 'present';
                $roster = SessionRosterEntry::where('class_session_id', $intent->class_session_id)->where('enrollment_id', $enrollment->id)->first();
                $values = [
                    'enrollment_id' => $enrollment->id, 'lesson_id' => $intent->session->lesson_id,
                    'class_session_id' => $intent->class_session_id, 'session_roster_entry_id' => $roster?->id,
                    'status' => $status, 'method' => 'qr', 'access_source' => $accessSource,
                    'observed_at' => $intent->accepted_at, 'recorded_at' => $now,
                ];
                if ($canonical) {
                    $before = $canonical->only(['class_session_id', 'status', 'method', 'access_source', 'observed_at', 'recorded_at']);
                    if ($canonical->session_roster_entry_id && $canonical->session_roster_entry_id !== $roster?->id) {
                        SessionRosterEntry::whereKey($canonical->session_roster_entry_id)->update(['status' => 'void']);
                    }
                    $canonical->update($values);
                    $attendance = $canonical;
                    AttendanceRevision::create(['attendance_id' => $attendance->id, 'actor_id' => $student->id,
                        'before_values' => $before, 'after_values' => $attendance->fresh()->only(array_keys($before)),
                        'reason' => 'Student completed an authorized QR check-in.']);
                } else {
                    $attendance = Attendance::create($values);
                }
                $intent->update(['consumed_at' => $now]);
                $roster?->update(['status' => $status, 'denial_reason' => null]);
                Audit::record($student, 'attendance_recorded', $attendance, ['status' => $status], null, $intent->session->offering->instructor_id);

                return $this->result($attendance, $enrollment, false);
            }, 3);
        } catch (ValidationException $exception) {
            if ($denial) {
                $this->deny(...$denial);
                if ($denial[3] === 'payment_required') {
                    SessionRosterEntry::where('class_session_id', $denial[0]->id)->where('enrollment_id', $denial[2]?->id)
                        ->update(['denial_reason' => 'payment_required']);
                }
            }
            throw $exception;
        }
    }

    public function captureRoster(ClassSession $session): void
    {
        DB::transaction(function () use ($session) {
            $session = ClassSession::lockForUpdate()->findOrFail($session->id);
            if ($session->roster_captured_at || $session->status === 'cancelled' || now()->lt($session->attendance_opens_at)) {
                return;
            }

            $memberships = GroupMembership::with('enrollment')->where('group_id', $session->group_id)
                ->where('starts_at', '<=', $session->attendance_opens_at)
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $session->attendance_opens_at))->get();
            foreach ($memberships as $membership) {
                if ($membership->enrollment->course_offering_id !== $session->course_offering_id) {
                    continue;
                }
                SessionRosterEntry::firstOrCreate([
                    'class_session_id' => $session->id, 'enrollment_id' => $membership->enrollment_id,
                ], ['group_id' => $session->group_id, 'status' => 'expected', 'captured_at' => now()]);
            }
            $session->update(['roster_captured_at' => now()]);
        });
    }

    public function finalize(ClassSession $session): bool
    {
        return DB::transaction(function () use ($session) {
            $session = ClassSession::lockForUpdate()->findOrFail($session->id);
            if ($session->status !== 'completed' || $session->absences_finalized_at
                || $session->intents()->whereNull('consumed_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
                return false;
            }
            $this->captureRoster($session);
            foreach ($session->rosterEntries()->with('enrollment')->lockForUpdate()->get() as $entry) {
                $existing = Attendance::where('enrollment_id', $entry->enrollment_id)->where('lesson_id', $session->lesson_id)->first();
                if ($existing) {
                    if (in_array($existing->status, ['present', 'late'], true)) {
                        $entry->update(['status' => $existing->status]);
                    }

                    continue;
                }
                $attendance = Attendance::create([
                    'enrollment_id' => $entry->enrollment_id, 'lesson_id' => $session->lesson_id,
                    'class_session_id' => $session->id, 'session_roster_entry_id' => $entry->id,
                    'status' => 'absent', 'method' => 'finalization', 'recorded_at' => now(),
                ]);
                $entry->update(['status' => 'absent']);
                Audit::record(null, 'attendance_finalized', $attendance, ['status' => 'absent'], null, $session->offering->instructor_id);
            }
            $session->update(['absences_finalized_at' => now()]);

            return true;
        });
    }

    public function correct(User $actor, ClassSession $session, Enrollment $enrollment, array $data): Attendance
    {
        return DB::transaction(function () use ($actor, $session, $enrollment, $data) {
            $session = ClassSession::with('offering')->lockForUpdate()->findOrFail($session->id);
            abort_unless($session->offering->instructor_id === $actor->id && $enrollment->course_offering_id === $session->course_offering_id, 403);
            $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollment->id);
            $attendance = Attendance::where('enrollment_id', $enrollment->id)->where('lesson_id', $session->lesson_id)->lockForUpdate()->first();
            $before = $attendance?->only(['class_session_id', 'status', 'method', 'access_source', 'observed_at', 'recorded_at']);
            $successful = in_array($data['status'], ['present', 'late'], true);
            $wasSuccessful = $attendance && in_array($attendance->status, ['present', 'late'], true);
            $accessSource = $attendance?->access_source;
            if ($successful && ! $wasSuccessful) {
                $accessSource = $this->access->source($enrollment, now());
                if ($accessSource === null && empty($data['payment_exception'])) {
                    throw ValidationException::withMessages(['payment_exception' => __('ui.payment_exception_required')]);
                }
                if ($accessSource === null) {
                    $accessSource = 'instructor_exception';
                }
            }
            $values = [
                'class_session_id' => $session->id, 'status' => $data['status'], 'method' => 'instructor',
                'access_source' => $successful ? $accessSource : null,
                'observed_at' => $data['observed_at'] ?? now(), 'recorded_at' => now(),
            ];
            $roster = SessionRosterEntry::where('class_session_id', $session->id)->where('enrollment_id', $enrollment->id)->first();
            if ($attendance) {
                $attendance->update($values);
            } else {
                $attendance = Attendance::create($values + ['enrollment_id' => $enrollment->id, 'lesson_id' => $session->lesson_id, 'session_roster_entry_id' => $roster?->id]);
            }
            $roster?->update(['status' => $data['status'], 'denial_reason' => null]);
            AttendanceRevision::create([
                'attendance_id' => $attendance->id, 'actor_id' => $actor->id, 'before_values' => $before,
                'after_values' => $attendance->fresh()->only(['class_session_id', 'status', 'method', 'access_source', 'observed_at', 'recorded_at']),
                'reason' => $data['reason'],
            ]);
            Audit::record($actor, 'attendance_corrected', $attendance, ['before' => $before, 'after' => $attendance->toArray()], $data['reason'], $session->offering->instructor_id);

            return $attendance;
        }, 3);
    }

    public function authorizeMakeup(User $actor, ClassSession $session, Enrollment $enrollment, string $reason): AttendanceAuthorization
    {
        abort_unless($session->offering->instructor_id === $actor->id && $session->course_offering_id === $enrollment->course_offering_id, 403);
        $authorization = AttendanceAuthorization::updateOrCreate(
            ['class_session_id' => $session->id, 'enrollment_id' => $enrollment->id],
            ['authorized_by' => $actor->id, 'reason' => $reason, 'revoked_at' => null],
        );
        Audit::record($actor, 'attendance_makeup_authorized', $authorization, [], $reason, $session->offering->instructor_id);

        return $authorization;
    }

    private function eligibleForGroup(ClassSession $session, Enrollment $enrollment): bool
    {
        return $enrollment->group_id === $session->group_id
            || AttendanceAuthorization::where('class_session_id', $session->id)->where('enrollment_id', $enrollment->id)->whereNull('revoked_at')->exists();
    }

    private function deny(ClassSession $session, User $student, ?Enrollment $enrollment, string $reason): void
    {
        AttendanceDenial::create(['class_session_id' => $session->id, 'enrollment_id' => $enrollment?->id, 'user_id' => $student->id, 'reason' => $reason, 'attempted_at' => now()]);
    }

    private function result(Attendance $attendance, Enrollment $enrollment, bool $duplicate): array
    {
        $count = $this->access->attendedCount($enrollment);

        return ['attendance' => $attendance, 'duplicate' => $duplicate, 'attended_count' => $count,
            'remaining_free' => max(0, (int) config('academic.free_attended_lessons', 2) - $count)];
    }
}
