<?php

namespace App\Http\Controllers;

use App\Contracts\CourseAccess;
use App\Models\AuditEvent;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\PaymentProof;
use App\Models\Semester;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $r, CourseAccess $access)
    {
        $user = $r->user();
        if (! $user->isInstructor()) {
            $enrollments = $user->enrollments()
                ->with(['offering.course', 'offering.semester', 'group', 'latestPaymentProof', 'accessWaivers'])
                ->withCount([
                    'attendances as attended_count' => fn ($query) => $query->whereIn('status', ['present', 'late']),
                    'attendances as absent_count' => fn ($query) => $query->where('status', 'absent'),
                ])->latest()->get();
            foreach ($enrollments as $enrollment) {
                $attended = $access->attendedCount($enrollment);
                $source = $access->source($enrollment, now());
                $state = match (true) {
                    $enrollment->latestPaymentProof?->status === 'approved' => 'payment_approved',
                    $source === 'waiver' => 'waived',
                    $enrollment->latestPaymentProof?->status === 'pending' => 'payment_pending',
                    $enrollment->latestPaymentProof?->status === 'rejected' => 'payment_rejected',
                    $enrollment->latestPaymentProof?->status === 'revoked' => 'payment_revoked',
                    $attended < (int) config('academic.free_attended_lessons', 2) => 'free_access',
                    default => 'payment_required',
                };
                $enrollment->setAttribute('payment_state', $state);
            }
            $activeEnrollments = $enrollments->where('status', 'enrolled');
            $futureSessions = ClassSession::query()
                ->where('status', 'published')
                ->where('scheduled_start_at', '>', now())
                ->where(function ($query) use ($activeEnrollments) {
                    if ($activeEnrollments->isEmpty()) {
                        $query->whereRaw('1 = 0');
                    }

                    foreach ($activeEnrollments as $enrollment) {
                        $query->orWhere(fn ($pair) => $pair
                            ->where('course_offering_id', $enrollment->course_offering_id)
                            ->where('group_id', $enrollment->group_id));
                    }
                });
            $nextSession = (clone $futureSessions)->with(['offering', 'lesson', 'group'])->orderBy('scheduled_start_at')->first();
            $attendedCount = $enrollments->sum('attended_count');
            $absenceCount = $enrollments->sum('absent_count');
            $attendanceTotal = $attendedCount + $absenceCount;
            $insights = [
                'active_courses' => $activeEnrollments->count(),
                'attendance_rate' => $attendanceTotal > 0 ? (int) round(($attendedCount / $attendanceTotal) * 100) : null,
                'upcoming_classes' => $activeEnrollments->isEmpty() ? 0 : (clone $futureSessions)->count(),
            ];

            return view('student.dashboard', compact('enrollments', 'nextSession', 'insights'));
        }

        $todaySessions = ClassSession::whereHas('offering', fn ($q) => $q->where('instructor_id', $user->id))
            ->whereBetween('scheduled_start_at', [now()->subDay(), now()->addDay()])->with(['offering', 'group', 'lesson'])
            ->orderBy('scheduled_start_at')->get()->filter(fn (ClassSession $session) => $session->scheduled_start_at->setTimezone($session->timezone)->isSameDay(now($session->timezone)));

        return view('instructor.dashboard', [
            'offerings' => CourseOffering::where('instructor_id', $user->id)->with('semester')->withCount('enrollments')->latest()->limit(6)->get(),
            'semesterCount' => Semester::where('instructor_id', $user->id)->whereNull('archived_at')->count(),
            'courseCount' => Course::where('instructor_id', $user->id)->whereNull('archived_at')->count(),
            'studentCount' => Enrollment::whereHas('offering', fn ($q) => $q->where('instructor_id', $user->id))->where('status', 'enrolled')->distinct()->count('student_id'),
            'todaySessions' => $todaySessions,
            'unresolvedSessions' => ClassSession::whereHas('offering', fn ($q) => $q->where('instructor_id', $user->id))
                ->whereIn('status', ['published', 'in_progress'])->where('scheduled_end_at', '<', now())->count(),
            'pendingPaymentCount' => PaymentProof::where('status', 'pending')
                ->whereHas('enrollment.offering', fn ($q) => $q->where('instructor_id', $user->id))->count(),
        ]);
    }

    public function audit(Request $r)
    {
        return view('instructor.audit', ['events' => AuditEvent::where('instructor_id', $r->user()->id)->with('actor')->latest('id')->paginate(30)]);
    }
}
