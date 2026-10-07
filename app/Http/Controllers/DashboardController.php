<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
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
            return view('student.dashboard', ['enrollments' => $user->enrollments()->with(['offering.course', 'offering.semester', 'group'])->latest()->get()]);
        }

        return view('instructor.dashboard', [
            'offerings' => CourseOffering::where('instructor_id', $user->id)->with('semester')->withCount('enrollments')->latest()->limit(6)->get(),
            'semesterCount' => Semester::where('instructor_id', $user->id)->whereNull('archived_at')->count(),
            'courseCount' => Course::where('instructor_id', $user->id)->whereNull('archived_at')->count(),
            'studentCount' => Enrollment::whereHas('offering', fn ($q) => $q->where('instructor_id', $user->id))->where('status', 'enrolled')->distinct()->count('student_id'),
        ]);
    }

    public function audit(Request $r)
    {
        return view('instructor.audit', ['events' => AuditEvent::where('instructor_id', $r->user()->id)->with('actor')->latest('id')->paginate(30)]);
    }
}
