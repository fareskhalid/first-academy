<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class IdentityTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return $overrides + ['name' => 'طالب جديد', 'phone' => '٠١٠١٢٣٤٥٦٧٨', 'password' => 'StudentPassword123!', 'password_confirmation' => 'StudentPassword123!'];
    }

    public function test_registration_creates_regular_student_and_requires_separate_login(): void
    {
        $this->post('/register', $this->registration(['role' => 'instructor', 'status' => 'active', 'student_code' => 'ADMIN']))->assertRedirect('/login')->assertSessionHas('registered_code');
        $user = User::sole();
        $this->assertGuest();
        $this->assertSame('student', $user->role);
        $this->assertSame('+201012345678', $user->phone);
        $this->assertStringStartsWith('STU-', $user->student_code);
        $this->assertSame($user->phone, $user->profile->whatsapp_phone);
        $this->assertNotNull($user->profile->whatsapp_declared_at);
        $this->assertSame(0, Enrollment::count());
        $this->post('/login', ['identifier' => '01012345678', 'password' => 'StudentPassword123!'])
            ->assertRedirect('/dashboard')
            ->assertCookie(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertSame(5_256_000, config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
    }

    public function test_phone_aliases_are_unique_and_invalid_phones_are_rejected(): void
    {
        User::factory()->create(['phone' => '+201012345678']);
        foreach (['01012345678', '+20 10 1234 5678', '00201012345678'] as $phone) {
            $this->post('/register', $this->registration(['phone' => $phone]))->assertSessionHasErrors('phone');
        }
        $this->post('/register', $this->registration(['phone' => '012345']))->assertSessionHasErrors('phone');
    }

    public function test_registration_uses_phone_for_whatsapp_and_ignores_removed_profile_fields(): void
    {
        $this->get('/register')->assertOk()
            ->assertDontSee('name="whatsapp_same"', false)
            ->assertDontSee('name="whatsapp_declared"', false)
            ->assertDontSee('name="university"', false)
            ->assertDontSee('name="university_student_id"', false);
        $this->post('/register', $this->registration([
            'whatsapp_phone' => '+966501234567',
            'university' => 'Should be ignored',
            'university_student_id' => 'SHOULD-BE-IGNORED',
        ]))->assertRedirect('/login');
        $profile = User::sole()->profile;
        $this->assertSame('+201012345678', $profile->whatsapp_phone);
        $this->assertNull($profile->university);
        $this->assertNull($profile->university_student_id);
    }

    public function test_student_code_and_phone_share_login_throttling(): void
    {
        $user = User::factory()->create(['phone' => '+201012345678']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['identifier' => $i % 2 ? $user->student_code : '01012345678', 'password' => 'wrong'])->assertSessionHasErrors('identifier');
        }
        $this->post('/login', ['identifier' => strtolower($user->student_code), 'password' => 'TestPassword123!'])->assertSessionHasErrors('identifier');
        $this->assertGuest();
        RateLimiter::clear('login:user:'.$user->id);
        $this->post('/login', ['identifier' => strtolower($user->student_code), 'password' => 'TestPassword123!'])->assertRedirect('/dashboard');
    }

    public function test_anonymous_users_cannot_browse_or_mutate_course_data(): void
    {
        $offering = $this->offering();
        foreach (['/dashboard', '/student/courses', '/student/courses/'.$offering->id, '/instructor/offerings', '/notifications'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->get('/invitations/secret')->assertNotFound();
        $this->post('/instructor/offerings/'.$offering->id.'/enrollments', ['identifier' => 'STU-000001', 'group_id' => $offering->groups->first()->id])->assertRedirect('/login');
        $this->assertDatabaseCount('enrollments', 0);
        $this->get('/verify-email')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    }

    public function test_profile_changes_require_password_and_invalidate_other_sessions(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);
        $data = ['name' => 'Updated', 'phone' => '01112345678', 'whatsapp_phone' => '+201112345678', 'whatsapp_declared' => '1'];
        $this->put('/profile', $data + ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->put('/profile', $data + ['current_password' => 'TestPassword123!'])->assertRedirect();
        $this->assertSame(2, $user->fresh()->session_version);
        $this->assertSame('+201112345678', $user->fresh()->phone);
        $this->signIn($user->fresh())->withSession(['auth_version' => 1])->get('/dashboard')->assertRedirect('/login');
    }

    public function test_password_change_does_not_leak_secrets_into_audit(): void
    {
        $user = User::factory()->create();
        $this->signIn($user)->put('/profile/password', ['current_password' => 'TestPassword123!', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertRedirect('/profile');
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertSame(2, $user->fresh()->session_version);
        $this->assertStringNotContainsString('Password', AuditEvent::get()->toJson());
    }

    public function test_suspension_and_expired_temporary_passwords_block_login(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);
        $this->post('/login', ['identifier' => $user->phone, 'password' => 'TestPassword123!'])->assertSessionHasErrors();
        $user->update(['status' => 'active', 'must_change_password' => true, 'temporary_password_expires_at' => now()->subMinute()]);
        $this->post('/login', ['identifier' => $user->phone, 'password' => 'TestPassword123!'])->assertSessionHasErrors();
    }

    public function test_temporary_password_forces_change_before_course_access(): void
    {
        $user = User::factory()->create(['must_change_password' => true, 'temporary_password_expires_at' => now()->addHour()]);
        $this->post('/login', ['identifier' => $user->phone, 'password' => 'TestPassword123!'])->assertRedirect('/profile');
        $this->get('/dashboard')->assertRedirect('/profile');
        $this->get('/profile')->assertOk();
        $this->put('/profile/password', ['current_password' => 'TestPassword123!', 'password' => 'ChangedPassword123!', 'password_confirmation' => 'ChangedPassword123!'])->assertRedirect('/profile');
        $this->get('/dashboard')->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_arabic_layout_and_validation_and_saved_locale(): void
    {
        $this->withUnencryptedCookie('locale', 'ar')->get('/register')->assertOk()->assertSee('dir="rtl"', false)->assertSee('إنشاء حساب طالب');
        $this->post('/register', [])->assertSessionHasErrors('phone');
        $user = User::factory()->create();
        $this->signIn($user)->postJson('/locale', ['locale' => 'ar'])->assertOk();
        $this->assertSame('ar', $user->fresh()->locale);
        $this->postJson('/locale', ['locale' => 'fr'])->assertUnprocessable();
    }
}
