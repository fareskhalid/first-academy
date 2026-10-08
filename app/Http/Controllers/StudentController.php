<?php

namespace App\Http\Controllers;

use App\Actions\Academics\EnrollmentManager;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\EnrollmentInvitation;
use App\Contracts\CourseAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    public function index()
    {
        abort_if(auth()->user()->isInstructor(), 403);

        return view('student.courses');
    }

    public function show(CourseOffering $offering, CourseAccess $access)
    {
        abort_if(auth()->user()->isInstructor(), 403);
        Gate::authorize('view', $offering);
        $offering->load(['course', 'semester', 'groups', 'lessons']);
        $enrollment = $offering->enrollments()->with([
            'group', 'memberships.group', 'transferRequests.group',
            'attendances' => fn ($query) => $query->with(['lesson', 'session'])->latest('recorded_at'),
        ])->where('student_id', auth()->id())->firstOrFail();
        $sessions = $offering->classSessions()->whereIn('status', ['published', 'in_progress', 'completed'])
            ->where(fn ($query) => $query->where('group_id', $enrollment->group_id)
                ->orWhereHas('authorizations', fn ($authorization) => $authorization->where('enrollment_id', $enrollment->id)->whereNull('revoked_at')))
            ->with(['lesson', 'group'])->orderBy('scheduled_start_at')->get();
        $attendedCount = $access->attendedCount($enrollment);
        $remainingFree = max(0, (int) config('academic.free_attended_lessons', 2) - $attendedCount);
        $accessSource = $access->source($enrollment, now());

        return view('student.course', compact('offering', 'enrollment', 'sessions', 'attendedCount', 'remainingFree', 'accessSource'));
    }

    public function transfer(Request $r, Enrollment $enrollment, EnrollmentManager $manager)
    {
        $data = $r->validate(['group_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:500']]);
        $manager->requestTransfer($r->user(), $enrollment, (int) $data['group_id'], $data['reason']);

        return back()->with('status_key', 'request_sent');
    }

    private function invitation(string $token): EnrollmentInvitation
    {
        abort_if(auth()->user()->isInstructor(), 403);

        return EnrollmentInvitation::with(['offering', 'group'])->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now())->where(fn ($q) => $q->whereNull('student_id')->orWhere('student_id', auth()->id()))->firstOrFail();
    }

    public function invitationShow(string $token)
    {
        $invitation = $this->invitation($token);

        return view('student.invitation', compact('invitation', 'token'));
    }

    public function invitationAccept(Request $r, string $token, EnrollmentManager $manager)
    {
        $invite = $this->invitation($token);
        $manager->enroll($r->user(), $invite->offering, $r->user(), $invite->group_id, $token);

        return redirect()->route('student.courses.show', $invite->offering)->with('status_key', 'enrollment_saved');
    }
}
