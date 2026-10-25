<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CourseMaterial;
use App\Models\CourseOffering;
use App\Support\Audit;
use App\Support\Notices;
use App\Support\YouTube;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CourseMaterialController extends Controller
{
    public function store(Request $request, CourseOffering $offering)
    {
        Gate::authorize('manage', $offering);
        $data = $this->validated($request, $offering);
        $stored = $this->storeFile($request->file('file'), $offering);

        try {
            $material = DB::transaction(function () use ($request, $offering, $data, $stored) {
                $material = $offering->materials()->create($this->attributes($data, $stored) + [
                    'uploaded_by' => $request->user()->id,
                    'published_at' => $data['status'] === 'published' ? now() : null,
                ]);
                $material->groups()->sync($data['group_ids']);
                Audit::record($request->user(), 'course_material_created', $material, [
                    'status' => $material->status,
                    'group_ids' => $data['group_ids'],
                ]);

                return $material;
            });
        } catch (Throwable $exception) {
            if ($stored) {
                Storage::disk($stored['file_disk'])->delete($stored['file_path']);
            }
            throw $exception;
        }

        if ($material->status === 'published') {
            $this->notifyStudents($material, $data['group_ids']);
        }

        return back()->with('status_key', 'material_saved');
    }

    public function update(Request $request, CourseOffering $offering, CourseMaterial $material)
    {
        Gate::authorize('manage', $offering);
        abort_unless($material->course_offering_id === $offering->id, 404);
        abort_if($material->status === 'archived', 422);

        $data = $this->validated($request, $offering, $material);
        $stored = $this->storeFile($request->file('file'), $offering);
        $oldPath = $material->file_path;
        $oldDisk = $material->file_disk;
        $oldStatus = $material->status;
        $oldGroupIds = $material->groups()->pluck('groups.id')->all();

        try {
            DB::transaction(function () use ($request, $material, $data, $stored, $oldStatus) {
                $material->update($this->attributes($data, $stored) + [
                    'published_at' => $data['status'] === 'published'
                        ? ($material->published_at ?? now())
                        : null,
                ]);
                $material->groups()->sync($data['group_ids']);
                Audit::record($request->user(), 'course_material_updated', $material, [
                    'status_before' => $oldStatus,
                    'status_after' => $data['status'],
                    'group_ids' => $data['group_ids'],
                ]);
            });
        } catch (Throwable $exception) {
            if ($stored) {
                Storage::disk($stored['file_disk'])->delete($stored['file_path']);
            }
            throw $exception;
        }

        if ($oldPath && ($stored || $data['type'] === 'youtube')) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        $notifyGroups = $oldStatus === 'published'
            ? array_values(array_diff($data['group_ids'], $oldGroupIds))
            : $data['group_ids'];
        if ($data['status'] === 'published' && $notifyGroups) {
            $this->notifyStudents($material->fresh(), $notifyGroups);
        }

        return back()->with('status_key', 'material_saved');
    }

    public function archive(Request $request, CourseOffering $offering, CourseMaterial $material)
    {
        Gate::authorize('manage', $offering);
        abort_unless($material->course_offering_id === $offering->id, 404);

        if (! $material->archived_at) {
            $material->update(['status' => 'archived', 'archived_at' => now()]);
            Audit::record($request->user(), 'course_material_archived', $material);
        }

        return back()->with('status_key', 'material_archived');
    }

    private function validated(Request $request, CourseOffering $offering, ?CourseMaterial $material = null): array
    {
        $type = $request->input('type');
        $fileRequired = in_array($type, ['pdf', 'image'], true)
            && (! $material || $material->type !== $type || ! $material->file_path);
        $fileRules = [$fileRequired ? 'required' : 'nullable', 'file'];
        if ($type === 'pdf') {
            $fileRules = [...$fileRules, 'max:25600', 'mimes:pdf', 'mimetypes:application/pdf'];
        } elseif ($type === 'image') {
            $fileRules = [...$fileRules, 'max:10240', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp'];
        } elseif ($type === 'youtube') {
            $fileRules = ['prohibited'];
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'title_ar' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(CourseMaterial::TYPES)],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'position' => ['required', 'integer', 'min:1', 'max:10000'],
            'lesson_id' => [
                'nullable',
                'integer',
                Rule::exists('lessons', 'id')->where('course_offering_id', $offering->id)->whereNull('archived_at'),
            ],
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('groups', 'id')->where('course_offering_id', $offering->id)->whereNull('archived_at'),
            ],
            'file' => $fileRules,
            'youtube_url' => [$type === 'youtube' ? 'required' : 'nullable', 'nullable', 'url:https', 'max:500'],
        ]);

        if ($type === 'youtube') {
            $data['youtube_video_id'] = YouTube::videoId($data['youtube_url']);
            if (! $data['youtube_video_id']) {
                throw ValidationException::withMessages(['youtube_url' => __('ui.invalid_youtube_url')]);
            }
        }

        return $data;
    }

    private function storeFile(?UploadedFile $file, CourseOffering $offering): ?array
    {
        if (! $file) {
            return null;
        }

        try {
            $path = $file->store('course-materials/'.$offering->id, 'local');
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => __('ui.material_upload_failed')]);
        }
        if (! $path) {
            throw ValidationException::withMessages(['file' => __('ui.material_upload_failed')]);
        }

        return [
            'file_disk' => 'local',
            'file_path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 240, ''),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
        ];
    }

    private function attributes(array $data, ?array $stored): array
    {
        $attributes = [
            'lesson_id' => $data['lesson_id'] ?? null,
            'title' => $data['title'],
            'title_ar' => $data['title_ar'],
            'description' => $data['description'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'type' => $data['type'],
            'status' => $data['status'],
            'position' => $data['position'],
            'youtube_video_id' => $data['type'] === 'youtube' ? $data['youtube_video_id'] : null,
        ];

        if ($stored) {
            return $attributes + $stored;
        }
        if ($data['type'] === 'youtube') {
            return $attributes + [
                'file_disk' => null,
                'file_path' => null,
                'original_name' => null,
                'mime_type' => null,
                'file_size' => null,
            ];
        }

        return $attributes;
    }

    private function notifyStudents(CourseMaterial $material, array $groupIds): void
    {
        $material->loadMissing('offering');
        $enrollments = $material->offering->enrollments()
            ->where('status', 'enrolled')
            ->whereIn('group_id', $groupIds)
            ->with('student')
            ->get();

        foreach ($enrollments as $enrollment) {
            if ($enrollment->student->status !== 'active') {
                continue;
            }
            Notices::sendOnce(
                $enrollment->student,
                'course-material-published-'.$material->id.'-'.$enrollment->student_id,
                'notice_material_published',
                [
                    'material' => $material->localized('title', $enrollment->student->locale),
                    'course' => $material->offering->localized('title', $enrollment->student->locale),
                ],
                route('student.courses.show', $material->course_offering_id, false).'#course-materials',
            );
        }
    }
}
