<?php

namespace App\Http\Controllers\Instructor;

use App\Actions\Identity\ManageStudentAccount;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $instructorId = $request->user()->id;
        $search = mb_substr(trim((string) $request->query('search')), 0, 100);
        $status = $request->query('status');

        $query = User::query()
            ->where('role', 'student')
            ->whereHas('enrollments.offering', fn (Builder $query) => $query->where('instructor_id', $instructorId));

        if ($search !== '') {
            $escaped = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $query) use ($escaped) {
                $query->where('name', 'like', $escaped)
                    ->orWhere('phone', 'like', $escaped)
                    ->orWhere('student_code', 'like', $escaped)
                    ->orWhereHas('profile', fn (Builder $profile) => $profile
                        ->where('whatsapp_phone', 'like', $escaped)
                        ->orWhere('university_student_id', 'like', $escaped));
            });
        }
        if (in_array($status, ['active', 'suspended'], true)) {
            $query->where('status', $status);
        }

        $students = $query->with([
            'profile',
            'enrollments' => fn ($query) => $query
                ->whereHas('offering', fn ($offering) => $offering->where('instructor_id', $instructorId))
                ->with(['offering.course', 'group'])
                ->latest('id'),
        ])->orderBy('name')->paginate(20)->withQueryString();

        return view('instructor.students', compact('students', 'search', 'status'));
    }

    public function account(Request $request, User $student, ManageStudentAccount $manager)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'reactivate', 'reset'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $temporary = $manager->handle($request->user(), $student, $data['action'], $data['reason']);

        return $temporary
            ? back()->with('temporary_password', $temporary)
            : back()->with('status_key', 'saved');
    }
}
