<?php

namespace App\Actions\Academics;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\EnrollmentInvitation;
use App\Models\Group;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EnrollmentManager
{
    private function fail(string $key): never
    {
        throw ValidationException::withMessages(['enrollment' => __('ui.'.$key)]);
    }

    private function group(CourseOffering $offering, int $id): Group
    {
        $group = $offering->groups()->whereNull('archived_at')->find($id);
        if (! $group) {
            $this->fail('invalid_group');
        }

        return $group;
    }

    private function capacity(Group $group): void
    {
        if ($group->capacity !== null && $group->enrollments()->where('status', 'enrolled')->count() >= $group->capacity) {
            $this->fail('group_full');
        }
    }

    public function enroll(User $actor, CourseOffering $offering, User $student, int $groupId, ?string $token = null): Enrollment
    {
        return DB::transaction(function () use ($actor, $offering, $student, $groupId, $token) {
            $offering = CourseOffering::lockForUpdate()->findOrFail($offering->id);
            if ($actor->isInstructor()) {
                Gate::forUser($actor)->authorize('manage', $offering);
            } else {
                abort_unless($actor->role === 'student' && $student->id === $actor->id, 403);
            }
            if ($offering->status !== 'open' || $offering->semester->archived_at || $offering->course->archived_at) {
                $this->fail('offering_closed');
            }
            if ($student->role !== 'student' || $student->status !== 'active') {
                $this->fail('student_unavailable');
            }
            if ($token) {
                $invite = EnrollmentInvitation::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
                if (! $invite || $invite->course_offering_id !== $offering->id || $invite->group_id !== $groupId || $invite->revoked_at || $invite->expires_at->lte(now()) || ($invite->student_id && $invite->student_id !== $student->id)) {
                    $this->fail('invalid_invitation');
                }
            } elseif (! $actor->isInstructor()) {
                $this->fail('invitation_required');
            }
            $group = $this->group($offering, $groupId);
            $existing = $offering->enrollments()->where('student_id', $student->id)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->status !== 'enrolled') {
                    $this->fail('restore_required');
                }

                return $existing;
            }
            $this->capacity($group);
            $enrollment = Enrollment::create(['student_id' => $student->id, 'course_offering_id' => $offering->id, 'group_id' => $group->id, 'status' => 'enrolled', 'joined_at' => now()]);
            $enrollment->memberships()->create(['group_id' => $group->id, 'starts_at' => now(), 'reason' => 'enrolled']);
            Audit::record($actor, 'enrolled', $enrollment, ['group_id' => $group->id], null, $offering->instructor_id);
            Notices::send($student, 'notice_enrolled', ['course' => $offering->title], route('student.courses.show', $offering, false));

            return $enrollment;
        }, 3);
    }

    public function change(User $actor, Enrollment $enrollment, string $action, string $reason, ?int $groupId = null): Enrollment
    {
        Gate::forUser($actor)->authorize('manage', $enrollment);

        return DB::transaction(function () use ($actor, $enrollment, $action, $reason, $groupId) {
            $offering = CourseOffering::lockForUpdate()->findOrFail($enrollment->course_offering_id);
            $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollment->id);
            if (! in_array($action, ['transfer', 'withdraw', 'restore'], true)) {
                $this->fail('invalid_enrollment_action');
            }
            if ($action !== 'withdraw' && $enrollment->student->status !== 'active') {
                $this->fail('student_unavailable');
            }
            $before = ['status' => $enrollment->status, 'group_id' => $enrollment->group_id];
            if ($action === 'withdraw') {
                if ($enrollment->status !== 'enrolled') {
                    return $enrollment;
                }
                $enrollment->memberships()->whereNull('ends_at')->update(['ends_at' => now()]);
                $enrollment->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
            } else {
                if ($offering->status !== 'open') {
                    $this->fail('offering_closed');
                }
                if ($action === 'restore' && $enrollment->status === 'enrolled') {
                    return $enrollment;
                }
                if ($action === 'transfer' && $enrollment->status !== 'enrolled') {
                    $this->fail('restore_required');
                }
                $group = $this->group($offering, $groupId ?? $enrollment->group_id);
                if ($enrollment->status === 'enrolled' && $enrollment->group_id === $group->id) {
                    return $enrollment;
                }
                $this->capacity($group);
                $enrollment->memberships()->whereNull('ends_at')->update(['ends_at' => now()]);
                $enrollment->update(['status' => 'enrolled', 'group_id' => $group->id, 'withdrawn_at' => null]);
                $enrollment->memberships()->create(['group_id' => $group->id, 'starts_at' => now(), 'reason' => $reason]);
            }
            $enrollment->transferRequests()->where('status', 'pending')->update(['status' => 'superseded', 'reviewed_at' => now()]);
            Audit::record($actor, $action, $enrollment, ['before' => $before, 'after' => ['status' => $enrollment->status, 'group_id' => $enrollment->group_id]], $reason, $offering->instructor_id);
            Notices::send($enrollment->student, 'notice_enrollment_changed', ['course' => $offering->title], route('student.courses.show', $offering, false));

            return $enrollment;
        }, 3);
    }

    public function requestTransfer(User $student, Enrollment $enrollment, int $groupId, string $reason): void
    {
        abort_unless($student->role === 'student' && $enrollment->student_id === $student->id, 403);
        DB::transaction(function () use ($student, $enrollment, $groupId, $reason) {
            $offering = CourseOffering::lockForUpdate()->findOrFail($enrollment->course_offering_id);
            $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollment->id);
            if ($offering->status !== 'open' || $enrollment->status !== 'enrolled') {
                $this->fail('offering_closed');
            }
            $group = $this->group($offering, $groupId);
            if ($group->id === $enrollment->group_id) {
                $this->fail('choose_other_group');
            }
            if ($enrollment->transferRequests()->where('status', 'pending')->exists()) {
                $this->fail('request_pending');
            }
            $request = $enrollment->transferRequests()->create(['group_id' => $groupId, 'reason' => $reason]);
            Audit::record($student, 'transfer_requested', $request, [], $reason, $offering->instructor_id);
            Notices::send($offering->instructor, 'notice_transfer', ['name' => $student->name, 'course' => $offering->title], route('instructor.offerings.show', $offering, false));
        });
    }
}
