<?php

namespace App\Http\Controllers;

use App\Actions\Academics\EnrollmentManager;
use App\Contracts\CourseAccess;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\EnrollmentInvitation;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index()
    {
        abort_if(auth()->user()->isInstructor(), 403);

        return view('student.courses');
    }

    public function lectures(Request $request)
    {
        abort_if($request->user()->isInstructor(), 403);
        $filters = $request->validate(['period' => ['nullable', Rule::in(['all', 'upcoming', 'past'])]]);
        $period = $filters['period'] ?? 'all';
        $now = now();
        $enrollments = $request->user()->enrollments()
            ->with(['offering.course', 'offering.semester', 'memberships'])
            ->get();
        $enrollmentIds = $enrollments->pluck('id');
        $baseQuery = $this->studentLectureQuery($enrollments);
        $insights = [
            'total' => (clone $baseQuery)->count(),
            'upcoming' => (clone $baseQuery)->where('scheduled_end_at', '>', $now)->count(),
            'today' => (clone $baseQuery)->whereBetween('scheduled_start_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])->count(),
            'attended' => $enrollmentIds->isEmpty() ? 0 : (clone $baseQuery)
                ->whereHas('attendances', fn (Builder $query) => $query
                    ->whereIn('enrollment_id', $enrollmentIds)
                    ->whereIn('status', ['present', 'late']))->count(),
        ];
        $nextLecture = $period === 'past' ? null : (clone $baseQuery)
            ->where('scheduled_end_at', '>', $now)
            ->with(['offering.course', 'group', 'lesson'])
            ->oldest('scheduled_start_at')
            ->first();
        $lectures = (clone $baseQuery)
            ->when($period === 'upcoming', fn (Builder $query) => $query->where('scheduled_end_at', '>', $now))
            ->when($period === 'past', fn (Builder $query) => $query->where('scheduled_end_at', '<=', $now))
            ->with([
                'offering.course', 'offering.semester', 'group', 'lesson',
                'attendances' => fn ($query) => $query->whereIn('enrollment_id', $enrollmentIds),
            ]);

        if ($period === 'upcoming') {
            $lectures->oldest('scheduled_start_at');
        } elseif ($period === 'past') {
            $lectures->latest('scheduled_start_at');
        } else {
            $lectures->orderByRaw('CASE WHEN scheduled_end_at > ? THEN 0 ELSE 1 END', [$now])
                ->orderByRaw('CASE WHEN scheduled_end_at > ? THEN scheduled_start_at END ASC', [$now])
                ->orderByRaw('CASE WHEN scheduled_end_at <= ? THEN scheduled_start_at END DESC', [$now]);
        }

        return view('student.lectures', [
            'lectures' => $lectures->paginate(20)->withQueryString(),
            'insights' => $insights,
            'nextLecture' => $nextLecture,
            'period' => $period,
        ]);
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

    private function studentLectureQuery(Collection $enrollments): Builder
    {
        $enrollmentIds = $enrollments->pluck('id');

        return ClassSession::query()
            ->whereIn('status', ['published', 'in_progress', 'completed'])
            ->where(function (Builder $access) use ($enrollments, $enrollmentIds) {
                $access->whereRaw('1 = 0');
                foreach ($enrollments as $enrollment) {
                    $memberships = $enrollment->memberships;
                    if ($memberships->isEmpty()) {
                        $access->orWhere(fn (Builder $pair) => $pair
                            ->where('course_offering_id', $enrollment->course_offering_id)
                            ->where('group_id', $enrollment->group_id)
                            ->where('scheduled_start_at', '>=', $enrollment->joined_at));

                        continue;
                    }

                    foreach ($memberships as $membership) {
                        $access->orWhere(function (Builder $pair) use ($enrollment, $membership) {
                            $pair->where('course_offering_id', $enrollment->course_offering_id)
                                ->where('group_id', $membership->group_id)
                                ->where('scheduled_start_at', '>=', $membership->starts_at)
                                ->when($membership->ends_at, fn (Builder $query, $endsAt) => $query->where('scheduled_start_at', '<', $endsAt));
                        });
                    }
                }
                if ($enrollmentIds->isNotEmpty()) {
                    $access->orWhereHas('authorizations', fn (Builder $authorization) => $authorization
                        ->whereIn('enrollment_id', $enrollmentIds)
                        ->whereNull('revoked_at'));
                }
            });
    }
}
