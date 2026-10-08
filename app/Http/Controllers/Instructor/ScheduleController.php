<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Services\AttendanceManager;
use App\Services\QrCredentialService;
use App\Services\ScheduleManager;
use App\Support\AcademicWeek;
use Carbon\CarbonImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $week = AcademicWeek::start($request->query('week'));
        $offerings = CourseOffering::where('instructor_id', $request->user()->id)->where('status', 'open')
            ->with(['groups' => fn ($query) => $query->whereNull('archived_at'), 'lessons' => fn ($query) => $query->whereNull('archived_at')])->get();
        $sessions = ClassSession::whereHas('offering', fn ($query) => $query->where('instructor_id', $request->user()->id))
            ->where('scheduled_start_at', '>=', $week->subDays(2)->setTimezone(config('database.timezone')))
            ->where('scheduled_start_at', '<', $week->addDays(9)->setTimezone(config('database.timezone')))
            ->with(['offering.course', 'group', 'lesson'])->withCount(['rosterEntries', 'attendances'])->orderBy('scheduled_start_at')->get()
            ->filter(function (ClassSession $session) use ($request) {
                $localWeek = AcademicWeek::start($request->query('week'), $session->timezone);
                $localStart = $session->scheduled_start_at->setTimezone($session->timezone);

                return $localStart->gte($localWeek) && $localStart->lt($localWeek->addWeek());
            })->values();
        $calendarEvents = $sessions->map(fn (ClassSession $session) => [
            'id' => (string) $session->id,
            'title' => $session->offering->title.' · '.$session->group->name.' · '.$session->lesson->title,
            'start' => $session->scheduled_start_at->toIso8601String(),
            'end' => $session->scheduled_end_at->toIso8601String(),
            'editable' => in_array($session->status, ['draft', 'published'], true),
            'backgroundColor' => match ($session->status) {
                'draft' => '#7b8499', 'published' => '#5946d2', 'in_progress' => '#1aa6a8',
                'completed' => '#2b9a70', default => '#b64a42',
            },
            'borderColor' => 'transparent',
            'extendedProps' => [
                'status' => $session->status,
                'editUrl' => route('instructor.sessions.show', $session),
                'moveUrl' => route('instructor.sessions.move', $session),
            ],
        ])->values();

        return view('instructor.schedule', compact('week', 'offerings', 'sessions', 'calendarEvents'));
    }

    public function store(Request $request, ScheduleManager $manager)
    {
        $data = $this->validated($request);
        $offering = CourseOffering::findOrFail($data['course_offering_id']);
        Gate::authorize('manage', $offering);
        $result = $manager->save($request->user(), $offering, $data);

        return back()->with('status_key', 'session_saved')->with('student_conflicts', $result['student_conflicts']);
    }

    public function update(Request $request, ClassSession $classSession, ScheduleManager $manager)
    {
        Gate::authorize('manage', $classSession->offering);
        $data = $this->validated($request, $classSession);
        $result = $manager->save($request->user(), $classSession->offering, $data, $classSession);

        return back()->with('status_key', 'session_saved')->with('student_conflicts', $result['student_conflicts']);
    }

    public function move(Request $request, ClassSession $classSession, ScheduleManager $manager)
    {
        Gate::authorize('manage', $classSession->offering);
        $data = $request->validate([
            'scheduled_start' => ['required', 'date'], 'scheduled_end' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'conflict_override_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $newStart = CarbonImmutable::parse($data['scheduled_start']);
        $newEnd = CarbonImmutable::parse($data['scheduled_end']);
        $oldStartTimestamp = $classSession->scheduled_start_at->getTimestamp();
        $shifted = fn ($value) => $newStart->addSeconds($value->getTimestamp() - $oldStartTimestamp);
        $newClose = $shifted($classSession->attendance_closes_at)->min($newEnd);
        $result = $manager->save($request->user(), $classSession->offering, [
            'course_offering_id' => $classSession->course_offering_id,
            'group_id' => $classSession->group_id, 'lesson_id' => $classSession->lesson_id,
            'scheduled_start' => $newStart->toIso8601String(), 'scheduled_end' => $newEnd->toIso8601String(),
            'attendance_opens' => $shifted($classSession->attendance_opens_at)->toIso8601String(),
            'attendance_closes' => $newClose->toIso8601String(),
            'late_after' => $shifted($classSession->late_after_at)->toIso8601String(),
            'location' => $classSession->location, 'reason' => $data['reason'],
            'conflict_override_reason' => $data['conflict_override_reason'] ?? null,
        ], $classSession);

        return response()->json([
            'message' => __('ui.calendar_rescheduled'),
            'student_conflicts' => $result['student_conflicts'],
            'start' => $result['session']->scheduled_start_at->toIso8601String(),
            'end' => $result['session']->scheduled_end_at->toIso8601String(),
        ]);
    }

    public function publish(Request $request, ScheduleManager $manager)
    {
        $data = $request->validate(['session_ids' => ['nullable', 'array'], 'session_ids.*' => ['integer'], 'week' => ['required', 'date']]);
        $query = ClassSession::whereHas('offering', fn ($q) => $q->where('instructor_id', $request->user()->id))->where('status', 'draft');
        if (! empty($data['session_ids'])) {
            $query->whereIn('id', $data['session_ids']);
        } else {
            $week = AcademicWeek::start($data['week']);
            $query->where('scheduled_start_at', '>=', $week->subDays(2)->setTimezone(config('database.timezone')))
                ->where('scheduled_start_at', '<', $week->addDays(9)->setTimezone(config('database.timezone')));
        }
        $sessions = $query->with('offering')->get();
        if (empty($data['session_ids'])) {
            $sessions = $sessions->filter(function (ClassSession $session) use ($data) {
                $localWeek = AcademicWeek::start($data['week'], $session->timezone);
                $localStart = $session->scheduled_start_at->setTimezone($session->timezone);

                return $localStart->gte($localWeek) && $localStart->lt($localWeek->addWeek());
            });
        }
        $count = $manager->publish($request->user(), $sessions);

        return back()->with('status_key', 'schedule_published')->with('published_count', $count);
    }

    public function copy(Request $request, ScheduleManager $manager)
    {
        $data = $request->validate(['course_offering_id' => ['required', 'integer'], 'week' => ['required', 'date']]);
        $offering = CourseOffering::findOrFail($data['course_offering_id']);
        Gate::authorize('manage', $offering);
        $manager->copyPreviousWeek($request->user(), $offering, AcademicWeek::start($data['week'], $offering->timezone));

        return back()->with('status_key', 'schedule_copied');
    }

    public function show(ClassSession $classSession)
    {
        Gate::authorize('manage', $classSession->offering);
        $classSession->load(['offering.course', 'group', 'lesson', 'rosterEntries.enrollment.student', 'attendances.enrollment.student']);
        $eligibleEnrollments = $classSession->offering->enrollments()->where('status', 'enrolled')->with(['student', 'group'])->get();

        return view('instructor.session', compact('classSession', 'eligibleEnrollments'));
    }

    public function transition(Request $request, ClassSession $classSession, ScheduleManager $manager)
    {
        Gate::authorize('manage', $classSession->offering);
        $data = $request->validate(['action' => ['required', Rule::in(['start', 'complete', 'cancel', 'close_attendance'])], 'reason' => ['required', 'string', 'max:500']]);
        $manager->transition($request->user(), $classSession, $data['action'], $data['reason']);
        if ($data['action'] === 'complete') {
            app(AttendanceManager::class)->finalize($classSession);
        }

        return back()->with('status_key', 'session_updated');
    }

    public function qr(ClassSession $classSession, QrCredentialService $credentials)
    {
        Gate::authorize('manage', $classSession->offering);
        $issued = $credentials->issue($classSession);
        $scanUrl = url()->secure(route('attendance.scan', ['token' => $issued['token']], false));
        $qrDataUri = (new Builder(
            writer: new SvgWriter, data: $scanUrl, encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 480, margin: 18,
        ))->build()->getDataUri();
        $classSession->load(['offering.course', 'group', 'lesson']);

        return view('instructor.qr', ['classSession' => $classSession, 'credential' => $issued['credential'],
            'shortCode' => $issued['shortCode'], 'qrDataUri' => $qrDataUri]);
    }

    public function correct(Request $request, ClassSession $classSession, AttendanceManager $attendance)
    {
        Gate::authorize('manage', $classSession->offering);
        $data = $request->validate([
            'enrollment_id' => ['required', 'integer'], 'status' => ['required', Rule::in(['present', 'late', 'absent', 'excused', 'void'])],
            'observed_at' => ['nullable', 'date'], 'reason' => ['required', 'string', 'max:500'], 'payment_exception' => ['sometimes', 'boolean'],
        ]);
        $enrollment = Enrollment::findOrFail($data['enrollment_id']);
        $attendance->correct($request->user(), $classSession, $enrollment, $data + ['payment_exception' => $request->boolean('payment_exception')]);

        return back()->with('status_key', 'attendance_corrected');
    }

    public function authorizeMakeup(Request $request, ClassSession $classSession, AttendanceManager $attendance)
    {
        Gate::authorize('manage', $classSession->offering);
        $data = $request->validate(['enrollment_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:500']]);
        $attendance->authorizeMakeup($request->user(), $classSession, Enrollment::findOrFail($data['enrollment_id']), $data['reason']);

        return back()->with('status_key', 'makeup_authorized');
    }

    private function validated(Request $request, ?ClassSession $session = null): array
    {
        return $request->validate([
            'course_offering_id' => [$session ? 'sometimes' : 'required', 'integer'],
            'group_id' => ['required', 'integer'], 'lesson_id' => ['required', 'integer'],
            'scheduled_start' => ['required', 'date'], 'scheduled_end' => ['required', 'date'],
            'attendance_opens' => ['nullable', 'date'], 'attendance_closes' => ['nullable', 'date'], 'late_after' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:180'], 'conflict_override_reason' => ['nullable', 'string', 'max:500'],
            'reason' => [$session ? 'required' : 'nullable', 'string', 'max:500'],
        ]) + ($session ? ['course_offering_id' => $session->course_offering_id] : []);
    }
}
