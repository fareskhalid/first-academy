<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Semester;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SetupController extends Controller
{
    private function model(string $kind): string
    {
        return $kind === 'semesters' ? Semester::class : Course::class;
    }

    public function index(Request $r)
    {
        $semesters = Semester::where('instructor_id', $r->user()->id)->latest()->get();
        $courses = Course::where('instructor_id', $r->user()->id)->latest()->get();
        $editSemester = $r->filled('semester') ? $semesters->firstWhere('id', (int) $r->semester) : null;
        $editCourse = $r->filled('course') ? $courses->firstWhere('id', (int) $r->course) : null;
        if (($r->filled('semester') && ! $editSemester) || ($r->filled('course') && ! $editCourse)) {
            abort(404);
        }

        return view('instructor.setup', compact('semesters', 'courses', 'editSemester', 'editCourse'));
    }

    public function save(Request $r, string $kind, ?int $id = null)
    {
        $class = $this->model($kind);
        $model = $id ? $class::findOrFail($id) : new $class;
        if ($id) {
            Gate::authorize('manage', $model);
        }
        $rules = ['name' => ['required', 'string', 'max:150']];
        if ($kind === 'semesters') {
            $rules += ['starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on']];
        } else {
            $rules += ['code' => ['required', 'string', 'max:40', Rule::unique('courses')->where('instructor_id', $r->user()->id)->ignore($id)], 'description' => ['nullable', 'string', 'max:3000']];
        }
        $data = $r->validate($rules);
        DB::transaction(function () use ($model, $data, $r, $id) {
            if ($id) {
                $model->newQuery()->whereKey($model->id)->lockForUpdate()->firstOrFail();
            }
            $model->fill($data + ['instructor_id' => $r->user()->id])->save();
            Audit::record($r->user(), $id ? 'academic_updated' : 'academic_created', $model, ['fields' => array_keys($data)]);
        });

        return redirect()->route('instructor.setup')->with('status_key', 'saved');
    }

    public function archive(Request $r, string $kind, int $id)
    {
        $model = $this->model($kind)::findOrFail($id);
        Gate::authorize('manage', $model);
        DB::transaction(function () use ($model, $r) {
            $model->newQuery()->whereKey($model->id)->lockForUpdate()->firstOrFail();
            if ($model->offerings()->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['archive' => __('ui.close_offerings_first')]);
            }
            $model->update(['archived_at' => now()]);
            Audit::record($r->user(), 'archived', $model);
        });

        return back()->with('status_key', 'saved');
    }
}
