<?php

namespace App\Http\Controllers\Instructor;

use App\Actions\Academics\EnrollmentManager;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\EnrollmentInvitation;
use App\Models\TransferRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notices;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    public function store(Request $r, CourseOffering $offering, EnrollmentManager $manager)
    {
        Gate::authorize('manage', $offering);
        $data = $r->validate(['identifier' => ['required', 'string', 'max:80'], 'group_id' => ['required', 'integer']]);
        $identifier = Phone::identifier($data['identifier']);
        $student = User::where('role', 'student')->where(str_starts_with($identifier, 'STU-') ? 'student_code' : 'phone', $identifier)->first();
        if (! $student) {
            throw ValidationException::withMessages(['identifier' => __('ui.student_unavailable')]);
        }
        $manager->enroll($r->user(), $offering, $student, (int) $data['group_id']);

        return back()->with('status_key', 'enrollment_saved');
    }

    public function update(Request $r, Enrollment $enrollment, EnrollmentManager $manager)
    {
        Gate::authorize('manage', $enrollment);
        $data = $r->validate(['action' => ['required', Rule::in(['transfer', 'withdraw', 'restore'])], 'reason' => ['required', 'string', 'max:500'], 'group_id' => ['nullable', 'integer']]);
        $manager->change($r->user(), $enrollment, $data['action'], $data['reason'], isset($data['group_id']) ? (int) $data['group_id'] : null);

        return back()->with('status_key', 'enrollment_saved');
    }

    public function invite(Request $r, CourseOffering $offering)
    {
        Gate::authorize('manage', $offering);
        $data = $r->validate(['group_id' => ['required', 'integer', Rule::exists('groups', 'id')->where('course_offering_id', $offering->id)->whereNull('archived_at')]]);
        $token = Str::random(64);
        DB::transaction(function () use ($r, $offering, $data, $token) {
            $invite = EnrollmentInvitation::create(['course_offering_id' => $offering->id, 'group_id' => $data['group_id'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
            Audit::record($r->user(), 'invitation_created', $invite, [], null, $offering->instructor_id);
        });

        return back()->with('invitation_url', route('invitations.show', $token));
    }

    public function revoke(Request $r, EnrollmentInvitation $invitation)
    {
        Gate::authorize('manage', $invitation->offering);
        DB::transaction(function () use ($r, $invitation) {
            $invitation->update(['revoked_at' => now()]);
            Audit::record($r->user(), 'invitation_revoked', $invitation, [], null, $invitation->offering->instructor_id);
        });

        return back()->with('status_key', 'saved');
    }

    public function review(Request $r, TransferRequest $transfer, EnrollmentManager $manager)
    {
        Gate::authorize('manage', $transfer->enrollment);
        $data = $r->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($r, $transfer, $manager, $data) {
            CourseOffering::lockForUpdate()->findOrFail($transfer->enrollment->course_offering_id);
            $transfer = TransferRequest::lockForUpdate()->findOrFail($transfer->id);
            if ($transfer->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => __('ui.already_reviewed')]);
            }
            if ($data['decision'] === 'approved') {
                $manager->change($r->user(), $transfer->enrollment, 'transfer', $data['reason'], $transfer->group_id);
            }
            $transfer->update(['status' => $data['decision'], 'reviewed_at' => now()]);
            Notices::send($transfer->enrollment->student, 'notice_transfer_reviewed', ['course' => $transfer->enrollment->offering->title], route('student.courses.show', $transfer->enrollment->offering, false));
            Audit::record($r->user(), 'transfer_reviewed', $transfer, ['decision' => $data['decision']], $data['reason'], $transfer->enrollment->offering->instructor_id);
        });

        return back()->with('status_key', 'saved');
    }

    public function account(Request $r, Enrollment $enrollment)
    {
        Gate::authorize('manage', $enrollment);
        $data = $r->validate(['action' => ['required', Rule::in(['suspend', 'reactivate', 'reset'])], 'reason' => ['required', 'string', 'max:500']]);
        $temporary = $data['action'] === 'reset' ? Str::password(16) : null;
        DB::transaction(function () use ($r, $enrollment, $data, $temporary) {
            $student = User::lockForUpdate()->findOrFail($enrollment->student_id);
            $changes = ['session_version' => $student->session_version + 1, 'remember_token' => Str::random(60)];
            if ($temporary) {
                $changes += ['password' => $temporary, 'must_change_password' => true, 'temporary_password_expires_at' => now()->addHour()];
            } else {
                $changes += ['status' => $data['action'] === 'suspend' ? 'suspended' : 'active'];
            }
            $student->update($changes);
            Audit::record($r->user(), 'account_'.$data['action'], $student, [], $data['reason'], $enrollment->offering->instructor_id);
        });

        return $temporary ? back()->with('temporary_password', $temporary) : back()->with('status_key', 'saved');
    }
}
