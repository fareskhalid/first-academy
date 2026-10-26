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
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'sort' => ['nullable', Rule::in(['name_asc', 'name_desc', 'newest', 'oldest'])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? '';
        $sort = $filters['sort'] ?? 'name_asc';

        $baseQuery = User::query()
            ->where('role', 'student')
            ->whereHas('enrollments.offering', fn (Builder $query) => $query->where('instructor_id', $instructorId));

        $insights = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', 'active')->count(),
            'suspended' => (clone $baseQuery)->where('status', 'suspended')->count(),
            'new' => (clone $baseQuery)->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        $query = clone $baseQuery;

        if ($search !== '') {
            $escaped = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $query) use ($escaped) {
                $query->where('name', 'like', $escaped)
                    ->orWhere('phone', 'like', $escaped)
                    ->orWhere('student_code', 'like', $escaped)
                    ->orWhereHas('profile', fn (Builder $profile) => $profile->where('whatsapp_phone', 'like', $escaped));
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        match ($sort) {
            'name_desc' => $query->orderByDesc('name'),
            'newest' => $query->latest('created_at'),
            'oldest' => $query->oldest('created_at'),
            default => $query->orderBy('name'),
        };

        $students = $query->with('profile')->paginate(20)->withQueryString();

        return view('instructor.students', compact('students', 'search', 'status', 'sort', 'insights'));
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
