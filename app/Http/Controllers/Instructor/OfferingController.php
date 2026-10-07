<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Semester;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfferingController extends Controller
{
    public function index(Request $r)
    {
        return view('instructor.offerings', [
            'offerings' => CourseOffering::where('instructor_id', $r->user()->id)->with(['course', 'semester'])->withCount('enrollments')->latest()->get(),
            'courses' => Course::where('instructor_id', $r->user()->id)->whereNull('archived_at')->get(),
            'semesters' => Semester::where('instructor_id', $r->user()->id)->whereNull('archived_at')->get(),
        ]);
    }

    public function show(CourseOffering $offering)
    {
        Gate::authorize('manage', $offering);
        $offering->load(['course', 'semester', 'groups', 'lessons', 'enrollments.student.profile', 'enrollments.group', 'enrollments.transferRequests.group', 'invitations.group']);

        return view('instructor.offering', compact('offering'));
    }

    public function save(Request $r, ?CourseOffering $offering = null)
    {
        if ($offering) {
            Gate::authorize('manage', $offering);
        }
        $r->merge(['fee' => str_replace('٫', '.', Phone::digits((string) $r->fee))]);
        $rules = ['title' => ['required', 'string', 'max:150'], 'timezone' => ['required', 'timezone'], 'fee' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/D'], 'currency' => ['required', Rule::in(['EGP', 'USD', 'SAR'])], 'self_enrollment' => ['sometimes', 'boolean']];
        if (! $offering) {
            $rules += ['course_id' => ['required', 'integer', Rule::exists('courses', 'id')->where('instructor_id', $r->user()->id)->whereNull('archived_at')], 'semester_id' => ['required', 'integer', Rule::exists('semesters', 'id')->where('instructor_id', $r->user()->id)->whereNull('archived_at')], 'uses_groups' => ['sometimes', 'boolean']];
        }
        $data = $r->validate($rules);
        $parts = explode('.', $data['fee']);
        $data['fee_minor'] = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
        unset($data['fee']);
        $data['self_enrollment'] = $r->boolean('self_enrollment');
        $offering = DB::transaction(function () use ($offering, $data, $r) {
            if ($offering) {
                $offering = CourseOffering::lockForUpdate()->findOrFail($offering->id);
                if ($offering->status === 'archived') {
                    abort(422);
                }
                $offering->update($data);
            } else {
                // Lock the parents in the same order as their archive actions.
                $course = Course::lockForUpdate()->findOrFail($data['course_id']);
                $semester = Semester::lockForUpdate()->findOrFail($data['semester_id']);
                abort_unless(
                    $course->instructor_id === $r->user()->id
                    && $semester->instructor_id === $r->user()->id
                    && ! $course->archived_at
                    && ! $semester->archived_at,
                    422
                );
                $offering = CourseOffering::create($data + ['instructor_id' => $r->user()->id, 'uses_groups' => $r->boolean('uses_groups')]);
                if (! $offering->uses_groups) {
                    $offering->groups()->create(['name' => 'General']);
                }
            }
            Audit::record($r->user(), 'offering_saved', $offering, ['fee_minor' => $offering->fee_minor, 'currency' => $offering->currency]);

            return $offering;
        });

        return redirect()->route('instructor.offerings.show', $offering)->with('status_key', 'saved');
    }

    public function status(Request $r, CourseOffering $offering)
    {
        Gate::authorize('manage', $offering);
        $data = $r->validate(['status' => ['required', Rule::in(['draft', 'open', 'completed', 'archived'])], 'reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($offering, $r, $data) {
            $offering = CourseOffering::lockForUpdate()->findOrFail($offering->id);
            if (($offering->status === 'archived' && $data['status'] !== 'archived')
                || ($offering->status === 'completed' && ! in_array($data['status'], ['completed', 'archived'], true))) {
                throw ValidationException::withMessages(['status' => __('ui.final_status')]);
            }
            if ($data['status'] === 'open' && ($offering->course->archived_at || $offering->semester->archived_at || ! $offering->groups()->whereNull('archived_at')->exists())) {
                throw ValidationException::withMessages(['status' => __('ui.cannot_open')]);
            }
            if ($data['status'] === 'draft' && $offering->enrollments()->exists()) {
                throw ValidationException::withMessages(['status' => __('ui.cannot_draft')]);
            }
            if (in_array($data['status'], ['completed', 'archived'])) {
                foreach ($offering->enrollments()->where('status', 'enrolled')->lockForUpdate()->get() as $enrollment) {
                    $enrollment->memberships()->whereNull('ends_at')->update(['ends_at' => now()]);
                    $enrollment->update(['status' => 'completed']);
                }
            }
            $before = $offering->status;
            $offering->update(['status' => $data['status']]);
            Audit::record($r->user(), 'offering_status', $offering, ['before' => $before, 'after' => $data['status']], $data['reason']);
        });

        return back()->with('status_key', 'saved');
    }

    public function group(Request $r, CourseOffering $offering, ?Group $group = null)
    {
        Gate::authorize('manage', $offering);
        if ($group) {
            abort_unless($group->course_offering_id === $offering->id, 404);
        }
        $data = $r->validate(['name' => ['required', 'string', 'max:80', Rule::unique('groups')->where('course_offering_id', $offering->id)->ignore($group?->id)], 'capacity' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        DB::transaction(function () use ($offering, $group, $data, $r) {
            $offering = CourseOffering::lockForUpdate()->findOrFail($offering->id);
            if ($offering->status === 'archived') {
                abort(422);
            }
            if ($group && isset($data['capacity']) && $group->enrollments()->where('status', 'enrolled')->count() > $data['capacity']) {
                throw ValidationException::withMessages(['capacity' => __('ui.capacity_too_small')]);
            }
            if (! $group && ! $offering->uses_groups && $offering->groups()->exists()) {
                throw ValidationException::withMessages(['name' => __('ui.general_group_only')]);
            }
            $group = $group ? tap($group)->update($data) : $offering->groups()->create($data);
            Audit::record($r->user(), 'group_saved', $group, [], null, $offering->instructor_id);
        });

        return back()->with('status_key', 'saved');
    }

    public function lesson(Request $r, CourseOffering $offering, ?Lesson $lesson = null)
    {
        Gate::authorize('manage', $offering);
        if ($lesson) {
            abort_unless($lesson->course_offering_id === $offering->id, 404);
        }
        abort_if($offering->status === 'archived', 422);
        $data = $r->validate(['title' => ['required', 'string', 'max:150'], 'position' => ['required', 'integer', 'min:1', 'max:1000', Rule::unique('lessons')->where('course_offering_id', $offering->id)->ignore($lesson?->id)]]);
        DB::transaction(function () use ($offering, $lesson, $data, $r) {
            $offering = CourseOffering::lockForUpdate()->findOrFail($offering->id);
            abort_if($offering->status === 'archived', 422);
            $lesson = $lesson ? tap($lesson)->update($data) : $offering->lessons()->create($data);
            Audit::record($r->user(), 'lesson_saved', $lesson, [], null, $offering->instructor_id);
        });

        return back()->with('status_key', 'saved');
    }

    public function archiveChild(Request $r, CourseOffering $offering, string $kind, int $id)
    {
        Gate::authorize('manage', $offering);
        DB::transaction(function () use ($r, $offering, $kind, $id) {
            CourseOffering::lockForUpdate()->findOrFail($offering->id);
            $item = ($kind === 'groups' ? $offering->groups() : $offering->lessons())->findOrFail($id);
            if ($kind === 'groups' && $item->enrollments()->where('status', 'enrolled')->exists()) {
                throw ValidationException::withMessages(['archive' => __('ui.group_has_students')]);
            }
            $item->update(['archived_at' => now()]);
            Audit::record($r->user(), 'archived', $item, [], null, $offering->instructor_id);
        });

        return back()->with('status_key', 'saved');
    }
}
