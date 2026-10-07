<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotice;
use App\Livewire\CourseDirectory;
use App\Livewire\NotificationCenter;
use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class NoticesTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_delivery_is_idempotent_and_student_cannot_read_anothers_notice(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $event = NotificationEvent::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'message_key' => 'notice_welcome', 'parameters' => ['name' => $user->name], 'url' => '/dashboard']);
        (new DeliverNotice($event->id))->handle();
        (new DeliverNotice($event->id))->handle();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull($event->fresh()->delivered_at);
        Livewire::actingAs($other)->test(NotificationCenter::class)->call('markRead', $event->id)->assertNotFound();
        Livewire::actingAs($user)->test(NotificationCenter::class)->assertSee($user->name)->call('markRead', $event->id);
        $this->assertNotNull($user->notifications()->sole()->read_at);
    }

    public function test_livewire_directory_is_authenticated_and_only_lists_open_public_offerings(): void
    {
        $visible = $this->offering(attributes: ['title' => 'Visible course']);
        $this->offering(attributes: ['title' => 'Private course', 'self_enrollment' => false]);
        $this->offering(attributes: ['title' => 'Draft course', 'status' => 'draft']);
        Livewire::test(CourseDirectory::class)->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(CourseDirectory::class)->assertSee('Visible course')->assertDontSee('Private course')->assertDontSee('Draft course')->set('search', 'Missing')->assertDontSee('Visible course');
    }

    public function test_localization_dictionaries_have_matching_keys_and_business_messages(): void
    {
        $en = trans('ui', [], 'en');
        $ar = trans('ui', [], 'ar');
        $this->assertSame(array_keys($en), array_keys($ar));
        $this->assertNotSame($en['notice_enrolled'], $ar['notice_enrolled']);
    }
}
