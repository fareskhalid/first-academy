<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Support\AcademicWeek;
use App\Support\Notices;

class ScheduleMaintenance
{
    public function run(): void
    {
        ClassSession::whereIn('status', ['published', 'in_progress'])->whereNull('roster_captured_at')
            ->where('attendance_opens_at', '<=', now())->limit(100)->get()->each(fn ($session) => app(AttendanceManager::class)->captureRoster($session));

        ClassSession::where('status', 'completed')->whereNull('absences_finalized_at')->limit(100)->get()
            ->each(fn ($session) => app(AttendanceManager::class)->finalize($session));

        $this->studentReminders();
        $this->instructorReminders();
    }

    private function studentReminders(): void
    {
        ClassSession::with('offering')->where('status', 'published')->where('scheduled_start_at', '>', now())
            ->where('scheduled_start_at', '<=', now()->addDay())->limit(200)->get()->each(function (ClassSession $session) {
                Enrollment::with('student')->where('course_offering_id', $session->course_offering_id)->where('group_id', $session->group_id)
                    ->where('status', 'enrolled')->get()->each(fn (Enrollment $enrollment) => Notices::sendOnce(
                        $enrollment->student, 'session-'.$session->id.'-upcoming-'.$enrollment->student_id, 'notice_session_upcoming',
                        ['course' => $session->offering->title, 'date' => $session->scheduled_start_at->setTimezone($session->timezone)->format('Y-m-d H:i')],
                        route('student.courses.show', $session->course_offering_id, false),
                    ));
            });
    }

    private function instructorReminders(): void
    {
        CourseOffering::with('instructor')->where('status', 'open')->get()->each(function (CourseOffering $offering) {
            $now = now($offering->timezone);
            if ($now->dayOfWeek !== 4 || $now->hour < 18) {
                return;
            }
            $nextWeek = AcademicWeek::start($now, $offering->timezone)->addWeek();
            $hasPublished = $offering->classSessions()->where('status', 'published')
                ->where('scheduled_start_at', '>=', $nextWeek->setTimezone(config('database.timezone')))
                ->where('scheduled_start_at', '<', $nextWeek->addWeek()->setTimezone(config('database.timezone')))->exists();
            if (! $hasPublished) {
                Notices::sendOnce($offering->instructor, 'offering-'.$offering->id.'-unpublished-'.$nextWeek->toDateString(),
                    'notice_schedule_unpublished', ['course' => $offering->title, 'date' => $nextWeek->toDateString()],
                    route('instructor.schedule', ['week' => $nextWeek->toDateString()], false));
            }
        });
    }
}
