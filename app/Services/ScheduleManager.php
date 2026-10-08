<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\SessionScheduleRevision;
use App\Models\User;
use App\Support\AcademicWeek;
use App\Support\Audit;
use App\Support\Notices;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleManager
{
    public function save(User $actor, CourseOffering $offering, array $data, ?ClassSession $session = null): array
    {
        return DB::transaction(function () use ($actor, $offering, $data, $session) {
            $offering = CourseOffering::with('semester')->lockForUpdate()->findOrFail($offering->id);
            abort_unless($offering->instructor_id === $actor->id, 403);
            abort_unless($offering->status === 'open', 422);

            if ($session) {
                $session = ClassSession::lockForUpdate()->findOrFail($session->id);
                abort_unless($session->course_offering_id === $offering->id, 404);
                abort_if(in_array($session->status, ['completed', 'cancelled'], true), 422);
            }

            $group = $offering->groups()->whereNull('archived_at')->findOrFail($data['group_id']);
            $lesson = $offering->lessons()->whereNull('archived_at')->findOrFail($data['lesson_id']);
            $start = CarbonImmutable::parse($data['scheduled_start'], $offering->timezone)->utc();
            $end = CarbonImmutable::parse($data['scheduled_end'], $offering->timezone)->utc();
            $localStart = $start->setTimezone($offering->timezone);
            $localEnd = $end->setTimezone($offering->timezone);

            if (! $end->gt($start)) {
                throw ValidationException::withMessages(['scheduled_end' => __('ui.session_end_after_start')]);
            }
            if ($localStart->toDateString() < $offering->semester->starts_on->toDateString()
                || $localEnd->toDateString() > $offering->semester->ends_on->toDateString()) {
                throw ValidationException::withMessages(['scheduled_start' => __('ui.session_outside_semester')]);
            }

            $opens = isset($data['attendance_opens'])
                ? CarbonImmutable::parse($data['attendance_opens'], $offering->timezone)->utc()
                : $start->subMinutes((int) config('academic.attendance_opens_minutes_before', 15));
            $closes = isset($data['attendance_closes'])
                ? CarbonImmutable::parse($data['attendance_closes'], $offering->timezone)->utc()
                : $start->addMinutes((int) config('academic.attendance_closes_minutes_after_start', 30))->min($end);
            $late = isset($data['late_after'])
                ? CarbonImmutable::parse($data['late_after'], $offering->timezone)->utc()
                : $start->addMinutes((int) config('academic.late_minutes_after_start', 10));

            if (! $opens->lt($closes) || $closes->gt($end) || $late->lt($opens) || $late->gt($closes)) {
                throw ValidationException::withMessages(['attendance_closes' => __('ui.invalid_attendance_window')]);
            }

            $conflicts = ClassSession::query()->where('id', '!=', $session?->id ?? 0)->where('status', '!=', 'cancelled')
                ->where('scheduled_start_at', '<', $end)->where('scheduled_end_at', '>', $start)
                ->where(fn ($query) => $query->where('group_id', $group->id)
                    ->orWhereHas('offering', fn ($offeringQuery) => $offeringQuery->where('instructor_id', $actor->id)))
                ->exists();
            if ($conflicts && blank($data['conflict_override_reason'] ?? null)) {
                throw ValidationException::withMessages(['scheduled_start' => __('ui.schedule_conflict')]);
            }

            $values = [
                'group_id' => $group->id, 'lesson_id' => $lesson->id, 'timezone' => $offering->timezone,
                'scheduled_start_at' => $start, 'scheduled_end_at' => $end,
                'attendance_opens_at' => $opens, 'attendance_closes_at' => $closes, 'late_after_at' => $late,
                'location' => $data['location'] ?? null,
                'conflict_override_reason' => $data['conflict_override_reason'] ?? null,
            ];
            $before = $session ? $this->snapshot($session) : null;
            $action = $session ? 'rescheduled' : 'created';

            if ($session) {
                $changed = collect($values)->contains(fn ($value, $key) => (string) $session->{$key} !== (string) $value);
                if ($changed && $session->status !== 'draft') {
                    app(QrCredentialService::class)->revoke($session);
                }
                $session->update($values);
            } else {
                $session = $offering->classSessions()->create($values + ['status' => 'draft']);
            }

            SessionScheduleRevision::create([
                'class_session_id' => $session->id, 'actor_id' => $actor->id, 'action' => $action,
                'before_values' => $before, 'after_values' => $this->snapshot($session->fresh()),
                'reason' => $data['reason'] ?? ($data['conflict_override_reason'] ?? null),
            ]);
            Audit::record($actor, 'session_'.$action, $session, ['before' => $before, 'after' => $this->snapshot($session)], $data['reason'] ?? null, $offering->instructor_id);

            if ($session->status === 'published') {
                $this->notifyRoster($session, 'notice_session_rescheduled', 'rescheduled-'.$session->updated_at->timestamp, $before['group_id'] ?? null);
            }

            return ['session' => $session, 'student_conflicts' => $this->studentConflictCount($session)];
        });
    }

    public function publish(User $actor, Collection $sessions): int
    {
        return DB::transaction(function () use ($actor, $sessions) {
            $published = 0;
            foreach ($sessions as $candidate) {
                $session = ClassSession::with('offering')->lockForUpdate()->findOrFail($candidate->id);
                abort_unless($session->offering->instructor_id === $actor->id, 403);
                if ($session->status !== 'draft') {
                    continue;
                }
                $localWeekStart = AcademicWeek::start($session->scheduled_start_at, $session->timezone);
                $isLate = now($session->timezone)->gte($localWeekStart);
                $session->update(['status' => 'published', 'published_at' => now()]);
                SessionScheduleRevision::create([
                    'class_session_id' => $session->id, 'actor_id' => $actor->id, 'action' => 'published',
                    'before_values' => ['status' => 'draft'], 'after_values' => ['status' => 'published'],
                    'reason' => $isLate ? 'Published after the teaching week began.' : null,
                ]);
                Audit::record($actor, 'session_published', $session, ['late' => $isLate], $isLate ? 'Late publication' : null, $session->offering->instructor_id);
                $this->notifyRoster($session, 'notice_session_published', 'published');
                $published++;
            }

            return $published;
        });
    }

    public function copyPreviousWeek(User $actor, CourseOffering $offering, CarbonImmutable $targetWeek): int
    {
        abort_unless($offering->instructor_id === $actor->id, 403);
        $sourceStart = $targetWeek->subWeek()->utc();
        $sourceEnd = $targetWeek->utc();
        $count = 0;

        DB::transaction(function () use ($actor, $offering, $sourceStart, $sourceEnd, &$count) {
            foreach ($offering->classSessions()->where('scheduled_start_at', '>=', $sourceStart)->where('scheduled_start_at', '<', $sourceEnd)->where('status', '!=', 'cancelled')->get() as $source) {
                $targetStart = $source->scheduled_start_at->copy()->addWeek();
                $values = [
                    'course_offering_id' => $offering->id, 'group_id' => $source->group_id, 'lesson_id' => $source->lesson_id,
                    'timezone' => $source->timezone, 'scheduled_start_at' => $targetStart,
                    'scheduled_end_at' => $source->scheduled_end_at->copy()->addWeek(), 'attendance_opens_at' => $source->attendance_opens_at->copy()->addWeek(),
                    'attendance_closes_at' => $source->attendance_closes_at->copy()->addWeek(), 'late_after_at' => $source->late_after_at->copy()->addWeek(),
                    'location' => $source->location, 'status' => 'draft',
                ];
                $copy = ClassSession::firstOrCreate([
                    'group_id' => $source->group_id, 'lesson_id' => $source->lesson_id, 'scheduled_start_at' => $targetStart,
                ], $values);
                if ($copy->wasRecentlyCreated) {
                    SessionScheduleRevision::create(['class_session_id' => $copy->id, 'actor_id' => $actor->id, 'action' => 'copied', 'after_values' => $this->snapshot($copy), 'reason' => 'Copied from session '.$source->id]);
                    $count++;
                }
            }
        });

        return $count;
    }

    public function transition(User $actor, ClassSession $session, string $action, string $reason): void
    {
        DB::transaction(function () use ($actor, $session, $action, $reason) {
            $session = ClassSession::with('offering')->lockForUpdate()->findOrFail($session->id);
            abort_unless($session->offering->instructor_id === $actor->id, 403);
            $before = $session->status;
            $next = match ($action) {
                'start' => $before === 'published' ? 'in_progress' : null,
                'complete' => $before === 'in_progress' ? 'completed' : null,
                'cancel' => in_array($before, ['draft', 'published'], true) ? 'cancelled' : null,
                'close_attendance' => $before,
                default => null,
            };
            if ($next === null) {
                throw ValidationException::withMessages(['status' => __('ui.invalid_session_transition')]);
            }

            $updates = [];
            if ($action === 'start') $updates = ['status' => $next, 'started_at' => now()];
            if ($action === 'complete') $updates = ['status' => $next, 'completed_at' => now()];
            if ($action === 'cancel') $updates = ['status' => $next, 'cancelled_at' => now(), 'cancellation_reason' => $reason];
            if ($action === 'close_attendance') $updates = ['attendance_closes_at' => now()];
            $session->update($updates);
            if (in_array($action, ['cancel', 'close_attendance'], true)) {
                app(QrCredentialService::class)->revoke($session);
            }
            SessionScheduleRevision::create(['class_session_id' => $session->id, 'actor_id' => $actor->id, 'action' => $action, 'before_values' => ['status' => $before], 'after_values' => $this->snapshot($session), 'reason' => $reason]);
            Audit::record($actor, 'session_'.$action, $session, ['before' => $before, 'after' => $session->status], $reason, $session->offering->instructor_id);
            if ($action === 'cancel') {
                $this->notifyRoster($session, 'notice_session_cancelled', 'cancelled-'.$session->qr_revision);
            }
        });
    }

    private function notifyRoster(ClassSession $session, string $key, string $event, ?int $previousGroupId = null): void
    {
        $session->loadMissing(['offering.course']);
        Enrollment::with('student')->where('course_offering_id', $session->course_offering_id)->whereIn('group_id', array_unique(array_filter([$session->group_id, $previousGroupId])))
            ->where('status', 'enrolled')->get()->each(function (Enrollment $enrollment) use ($session, $key, $event) {
                Notices::sendOnce($enrollment->student, 'session-'.$session->id.'-'.$event.'-'.$enrollment->student_id, $key, [
                    'course' => $session->offering->title,
                    'date' => $session->scheduled_start_at->setTimezone($session->timezone)->format('Y-m-d H:i'),
                ], route('student.courses.show', $session->course_offering_id, false));
            });
    }

    private function studentConflictCount(ClassSession $session): int
    {
        $studentIds = Enrollment::where('course_offering_id', $session->course_offering_id)->where('group_id', $session->group_id)
            ->where('status', 'enrolled')->pluck('student_id');
        if ($studentIds->isEmpty()) return 0;
        $otherOfferings = ClassSession::where('id', '!=', $session->id)->where('status', '!=', 'cancelled')
            ->where('course_offering_id', '!=', $session->course_offering_id)
            ->where('scheduled_start_at', '<', $session->scheduled_end_at)->where('scheduled_end_at', '>', $session->scheduled_start_at)
            ->pluck('course_offering_id');

        return Enrollment::whereIn('student_id', $studentIds)->whereIn('course_offering_id', $otherOfferings)->where('status', 'enrolled')->distinct()->count('student_id');
    }

    private function snapshot(ClassSession $session): array
    {
        return $session->only(['course_offering_id', 'group_id', 'lesson_id', 'scheduled_start_at', 'scheduled_end_at', 'attendance_opens_at', 'attendance_closes_at', 'late_after_at', 'location', 'status']);
    }
}
