<?php

namespace Tests\Feature;

use App\Actions\Academics\EnrollmentManager;
use App\Models\CourseMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseMaterialTest extends TestCase
{
    use RefreshDatabase;

    public function test_youtube_material_is_embedded_only_for_its_selected_groups_and_can_be_shared(): void
    {
        $offering = $this->offering();
        $lesson = $offering->lessons()->create(['title' => 'Introduction', 'title_ar' => 'المقدمة', 'position' => 1]);
        [$groupA, $groupB] = $offering->groups;
        $studentA = User::factory()->create();
        $studentB = User::factory()->create();
        app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $studentA, $groupA->id);
        app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $studentB, $groupB->id);

        $payload = [
            'title' => 'Introduction video',
            'title_ar' => 'فيديو المقدمة',
            'description' => 'Watch before class.',
            'description_ar' => 'شاهده قبل المحاضرة.',
            'type' => 'youtube',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'lesson_id' => $lesson->id,
            'group_ids' => [$groupA->id],
            'position' => 1,
            'status' => 'published',
        ];

        $this->signIn($offering->instructor)
            ->post(route('instructor.materials.store', $offering), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $material = CourseMaterial::sole();
        $this->assertSame('dQw4w9WgXcQ', $material->youtube_video_id);
        $this->assertSame([$groupA->id], $material->groups()->pluck('groups.id')->all());
        $this->assertDatabaseHas('notification_events', ['user_id' => $studentA->id, 'message_key' => 'notice_material_published']);
        $this->assertDatabaseMissing('notification_events', ['user_id' => $studentB->id, 'message_key' => 'notice_material_published']);
        $this->get(route('instructor.offerings.show', $offering))->assertOk()->assertSee('Introduction video');

        $this->signIn($studentA)
            ->get(route('student.courses.show', $offering))
            ->assertOk()
            ->assertSee('Introduction video')
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false);

        $this->signIn($studentB)
            ->get(route('student.courses.show', $offering))
            ->assertOk()
            ->assertDontSee('Introduction video');
        $this->get(route('course-materials.file', $material))->assertForbidden();

        $this->signIn($offering->instructor)
            ->put(route('instructor.materials.update', [$offering, $material]), array_replace($payload, ['group_ids' => [$groupA->id, $groupB->id]]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->signIn($studentB)
            ->get(route('student.courses.show', $offering))
            ->assertOk()
            ->assertSee('Introduction video');
        $this->assertDatabaseHas('notification_events', ['user_id' => $studentB->id, 'message_key' => 'notice_material_published']);
    }

    public function test_private_file_material_stays_hidden_until_published_and_archive_revokes_access(): void
    {
        Storage::fake('local');
        $offering = $this->offering();
        $group = $offering->groups->first();
        $student = User::factory()->create();
        app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $student, $group->id);

        $payload = [
            'title' => 'Course diagram',
            'title_ar' => 'مخطط الكورس',
            'type' => 'image',
            'file' => UploadedFile::fake()->image('diagram.png', 900, 600),
            'group_ids' => [$group->id],
            'position' => 1,
            'status' => 'draft',
        ];
        $this->signIn($offering->instructor)
            ->post(route('instructor.materials.store', $offering), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $material = CourseMaterial::sole();
        Storage::disk('local')->assertExists($material->file_path);
        $this->get(route('course-materials.file', $material))->assertOk();

        $update = [
            'title' => $material->title,
            'title_ar' => $material->title_ar,
            'type' => 'image',
            'group_ids' => [$group->id],
            'position' => 1,
            'status' => 'published',
        ];
        $this->put(route('instructor.materials.update', [$offering, $material]), $update)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->signIn($student)
            ->get(route('course-materials.file', $material))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->signIn($offering->instructor)
            ->post(route('instructor.materials.archive', [$offering, $material]))
            ->assertRedirect();
        Storage::disk('local')->assertExists($material->file_path);
        $this->signIn($student)->get(route('course-materials.file', $material))->assertForbidden();
    }

    public function test_material_rejects_foreign_groups_and_invalid_youtube_urls(): void
    {
        $offering = $this->offering();
        $foreign = $this->offering();
        $payload = [
            'title' => 'Video',
            'title_ar' => 'فيديو',
            'type' => 'youtube',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'group_ids' => [$foreign->groups->first()->id],
            'position' => 1,
            'status' => 'published',
        ];

        $this->signIn($offering->instructor)
            ->post(route('instructor.materials.store', $offering), $payload)
            ->assertSessionHasErrors('group_ids.0');
        $this->post(route('instructor.materials.store', $offering), array_replace($payload, [
            'youtube_url' => 'https://example.com/watch?v=dQw4w9WgXcQ',
            'group_ids' => [$offering->groups->first()->id],
        ]))->assertSessionHasErrors('youtube_url');
        $this->assertDatabaseCount('course_materials', 0);
    }
}
