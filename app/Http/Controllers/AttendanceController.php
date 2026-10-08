<?php

namespace App\Http\Controllers;

use App\Contracts\CourseAccess;
use App\Models\Attendance;
use App\Models\AttendanceIntent;
use App\Services\AttendanceManager;
use App\Services\QrCredentialService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function scan(Request $request, string $token, QrCredentialService $credentials)
    {
        abort_if($request->user()->isInstructor(), 403);
        $credential = $credentials->fromToken($token);

        return view('student.attendance-confirm', ['session' => $credential->session, 'token' => $token, 'intent' => null]);
    }

    public function start(Request $request, string $token, QrCredentialService $credentials, AttendanceManager $attendance)
    {
        abort_if($request->user()->isInstructor(), 403);
        $credential = $credentials->fromToken($token);
        $intent = $attendance->createIntent($credential, $request->user(), $this->browserHash($request));

        return redirect()->route('attendance.intent', $intent);
    }

    public function code(Request $request, QrCredentialService $credentials, AttendanceManager $attendance)
    {
        abort_if($request->user()->isInstructor(), 403);
        $data = $request->validate(['code' => ['required', 'alpha_num:ascii', 'size:6']]);
        $credential = $credentials->fromShortCode($data['code']);
        $intent = $attendance->createIntent($credential, $request->user(), $this->browserHash($request));

        return redirect()->route('attendance.intent', $intent);
    }

    public function intent(Request $request, AttendanceIntent $intent)
    {
        $this->authorizeIntent($request, $intent);
        if ($intent->consumed_at) {
            return redirect()->route('attendance.result', $intent);
        }
        $intent->load(['session.offering', 'session.group', 'session.lesson']);

        return view('student.attendance-confirm', ['session' => $intent->session, 'token' => null, 'intent' => $intent]);
    }

    public function record(Request $request, AttendanceIntent $intent, AttendanceManager $attendance)
    {
        abort_if($request->user()->isInstructor(), 403);
        $result = $attendance->record($intent, $request->user(), $this->browserHash($request));

        return redirect()->route('attendance.result', $intent)->with('attendance_duplicate', $result['duplicate']);
    }

    public function result(Request $request, AttendanceIntent $intent, CourseAccess $access)
    {
        $this->authorizeIntent($request, $intent);
        $intent->load('session');
        $attendance = Attendance::with(['session.offering.course', 'session.group', 'session.lesson'])
            ->where('enrollment_id', $intent->enrollment_id)->where('lesson_id', $intent->session->lesson_id)
            ->whereIn('status', ['present', 'late'])->firstOrFail();
        $count = $access->attendedCount($intent->enrollment);

        return view('student.attendance-result', ['attendance' => $attendance,
            'duplicate' => (bool) session('attendance_duplicate', false), 'attended_count' => $count,
            'remaining_free' => max(0, (int) config('academic.free_attended_lessons', 2) - $count)]);
    }

    private function browserHash(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }

    private function authorizeIntent(Request $request, AttendanceIntent $intent): void
    {
        abort_unless($intent->user_id === $request->user()->id
            && hash_equals($intent->browser_session_hash, $this->browserHash($request)), 403);
    }
}
