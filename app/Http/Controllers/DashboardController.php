<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Semester;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        if (! $user->isInstructor()) {
            $enrollments = $user->enrollments()->with(['offering.course', 'offering.semester', 'group'])->latest()->get();
            $nextSession = ClassSession::whereIn('course_offering_id', $enrollments->where('status', 'enrolled')->pluck('course_offering_id'))
                ->whereIn('group_id', $enrollments->where('status', 'enrolled')->pluck('group_id'))->where('status', 'published')
                ->where('scheduled_start_at', '>', now())->with(['offering', 'lesson', 'group'])->orderBy('scheduled_start_at')->first();

            return view('student.dashboard', compact('enrollments', 'nextSession'));
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
        ]);
    }

    public function audit(Request $r)
    {
        return view('instructor.audit', ['events' => AuditEvent::where('instructor_id', $r->user()->id)->with('actor')->latest('id')->paginate(30)]);
    }
}
