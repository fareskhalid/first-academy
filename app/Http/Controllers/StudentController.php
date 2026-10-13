<?php

namespace App\Http\Controllers;

use App\Actions\Academics\EnrollmentManager;
use App\Contracts\CourseAccess;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\EnrollmentInvitation;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Support\Phone;
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
        $attendanceRecorded = $enrollment->attendances->whereIn('status', ['present', 'late'])->count();
        $absenceCount = $enrollment->attendances->where('status', 'absent')->count();
        $attendanceTotal = $attendanceRecorded + $absenceCount;
        $attendanceRate = $attendanceTotal > 0 ? (int) round(($attendanceRecorded / $attendanceTotal) * 100) : null;
        $nextSession = $sessions->first(fn ($session) => $session->scheduled_start_at->isFuture());

        return view('student.course', compact('offering', 'enrollment', 'sessions', 'attendedCount', 'remainingFree', 'accessSource', 'attendanceRecorded', 'absenceCount', 'attendanceRate', 'nextSession'));
    }

    public function transfer(Request $r, Enrollment $enrollment, EnrollmentManager $manager)
    {
        $data = $r->validate(['group_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:500']]);
        $manager->requestTransfer($r->user(), $enrollment, (int) $data['group_id'], $data['reason']);

        return back()->with('status_key', 'request_sent');
    }

    private function invitation(string $token, ?User $student = null): EnrollmentInvitation
    {
        abort_if($student?->isInstructor(), 403);

        return EnrollmentInvitation::with(['offering', 'group'])->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now())
            ->where(fn ($query) => $query->whereNull('student_id')->when($student, fn ($query) => $query->orWhere('student_id', $student->id)))
            ->firstOrFail();
    }

    public function invitationShow(Request $request, string $token)
    {
        $invitation = $this->invitation($token, $request->user());

        return view('student.invitation', compact('invitation', 'token'));
    }

    public function invitationAccount(Request $request, string $token)
    {
        $this->invitation($token, $request->user());
        if ($request->user()) {
            return redirect()->route('invitations.show', $token);
        }

        $request->merge(['phone' => Phone::normalize((string) $request->input('phone'))]);
        $data = $request->validate(['phone' => ['required', new PhoneNumber]]);
        $request->session()->put('url.intended', route('invitations.show', $token));

        return User::where('phone', $data['phone'])->exists()
            ? redirect()->route('login', ['identifier' => $data['phone']])
            : redirect()->route('register', ['phone' => $data['phone']]);
    }

    public function invitationAccept(Request $r, string $token, EnrollmentManager $manager)
    {
        $invite = $this->invitation($token, $r->user());
        $manager->enroll($r->user(), $invite->offering, $r->user(), $invite->group_id, $token);

        return redirect()->route('student.courses.show', $invite->offering)->with('status_key', 'enrollment_saved');
    }
}
