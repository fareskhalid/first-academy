<?php

namespace Tests\Feature;

use App\Actions\Academics\EnrollmentManager;
use App\Models\AuditEvent;
use App\Models\CourseOffering;
use App\Models\EnrollmentInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_creates_semester_course_offering_groups_and_lessons(): void
    {
        $teacher = User::factory()->instructor()->create();
        $this->signIn($teacher);
        $this->post('/instructor/setup/semesters', ['name' => 'Autumn', 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31'])->assertRedirect();
        $this->post('/instructor/setup/courses', ['name' => 'Programming', 'code' => 'CS101', 'description' => 'Course'])->assertRedirect();
        $this->post('/instructor/offerings', ['title' => 'Programming Autumn', 'course_id' => 1, 'semester_id' => 1, 'timezone' => 'Africa/Cairo', 'uses_groups' => 1])->assertRedirect();
        $offering = CourseOffering::sole();
        $this->assertSame('draft', $offering->status);
        foreach (['Group A', 'Group B'] as $name) {
            $this->post('/instructor/offerings/'.$offering->id.'/groups', ['name' => $name, 'capacity' => 20])->assertRedirect();
        }
        $this->post('/instructor/offerings/'.$offering->id.'/lessons', ['title' => 'Introduction', 'position' => 1])->assertRedirect();
        $this->post('/instructor/offerings/'.$offering->id.'/status', ['status' => 'open', 'reason' => 'Ready'])->assertRedirect();
        $this->assertSame('open', $offering->fresh()->status);
        $this->assertDatabaseCount('groups', 2);
        $this->assertDatabaseCount('lessons', 1);
        foreach (['/dashboard', '/instructor/setup', '/instructor/offerings', '/instructor/offerings/'.$offering->id, '/instructor/audit'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_ungrouped_offering_has_one_general_group(): void
    {
        $existing = $this->offering();
        $this->signIn($existing->instructor);
        $this->post('/instructor/offerings', ['title' => 'General offering', 'course_id' => $existing->course_id, 'semester_id' => $existing->semester_id, 'timezone' => 'Africa/Cairo'])->assertRedirect();
        $general = CourseOffering::latest('id')->first();
        $this->assertFalse($general->uses_groups);
        $this->assertSame('General', $general->groups()->sole()->name);
        $this->post('/instructor/offerings/'.$general->id.'/groups', ['name' => 'Another'])->assertSessionHasErrors('name');
    }

    public function test_instructor_enrolls_student_in_two_offerings_and_duplicate_requests_are_idempotent(): void
    {
        $first = $this->offering();
        $second = $this->offering($first->instructor);
        $student = User::factory()->create();
        $manager = app(EnrollmentManager::class);
        foreach ([$first, $second] as $offering) {
            $manager->enroll($offering->instructor, $offering, $student, $offering->groups->first()->id);
        }
        $manager->enroll($first->instructor, $first, $student, $first->groups->last()->id);
        $this->assertDatabaseCount('enrollments', 2);
        $this->assertDatabaseCount('group_memberships', 2);
        $this->signIn($student);
        foreach (['/dashboard', '/student/courses', '/student/courses/'.$first->id, '/notifications', '/profile'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_capacity_and_cross_offering_group_are_enforced(): void
    {
        $offering = $this->offering();
        $other = $this->offering();
        $group = $offering->groups->first();
        $group->update(['capacity' => 1]);
        $first = User::factory()->create();
        app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $first, $group->id);
        $second = User::factory()->create();
        $this->signIn($offering->instructor)->post('/instructor/offerings/'.$offering->id.'/enrollments', ['identifier' => $second->student_code, 'group_id' => $group->id])->assertSessionHasErrors('enrollment');
        $this->post('/instructor/offerings/'.$offering->id.'/enrollments', ['identifier' => $second->student_code, 'group_id' => $other->groups->first()->id])->assertSessionHasErrors('enrollment');
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_transfer_withdraw_restore_preserve_canonical_enrollment_and_history(): void
    {
        $offering = $this->offering();
        $student = User::factory()->create();
        $manager = app(EnrollmentManager::class);
        $enrollment = $manager->enroll($offering->instructor, $offering, $student, $offering->groups->first()->id);
        $originalId = $enrollment->id;
        $this->signIn($offering->instructor);
        $this->put('/instructor/enrollments/'.$enrollment->id, ['action' => 'transfer', 'group_id' => $offering->groups->last()->id, 'reason' => 'Schedule preference'])->assertRedirect();
        $this->put('/instructor/enrollments/'.$enrollment->id, ['action' => 'withdraw', 'reason' => 'Break'])->assertRedirect();
        $this->signIn($offering->instructor)->put('/instructor/enrollments/'.$enrollment->id, ['action' => 'restore', 'group_id' => $offering->groups->first()->id, 'reason' => 'Returned'])->assertRedirect();
        $enrollment->refresh();
        $this->assertSame($originalId, $enrollment->id);
        $this->assertSame('enrolled', $enrollment->status);
        $this->assertSame(3, $enrollment->memberships()->count());
        $this->assertSame(1, $enrollment->memberships()->whereNull('ends_at')->count());
    }

    public function test_student_transfer_request_requires_owner_and_instructor_review(): void
    {
        $offering = $this->offering();
        $student = User::factory()->create();
        $enrollment = app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $student, $offering->groups->first()->id);
        $payload = ['group_id' => $offering->groups->last()->id, 'reason' => 'Changed schedule'];
        $this->signIn(User::factory()->create())->post('/student/enrollments/'.$enrollment->id.'/transfer', $payload)->assertForbidden();
        $this->signIn($student)->post('/student/enrollments/'.$enrollment->id.'/transfer', $payload)->assertRedirect();
        $transfer = $enrollment->transferRequests()->sole();
        $this->post('/student/enrollments/'.$enrollment->id.'/transfer', $payload)->assertSessionHasErrors('enrollment');
        $this->signIn(User::factory()->instructor()->create())->post('/instructor/transfers/'.$transfer->id, ['decision' => 'approved', 'reason' => 'Okay'])->assertForbidden();
        $this->signIn($offering->instructor)->post('/instructor/transfers/'.$transfer->id, ['decision' => 'approved', 'reason' => 'Space available'])->assertRedirect();
        $this->assertSame('approved', $transfer->fresh()->status);
        $this->assertSame($payload['group_id'], $enrollment->fresh()->group_id);
    }

    public function test_invitation_requires_sign_in_acceptance_and_current_validity(): void
    {
        $offering = $this->offering();
        $token = Str::random(64);
        $invite = EnrollmentInvitation::create(['course_offering_id' => $offering->id, 'group_id' => $offering->groups->first()->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);
        $this->get('/invitations/'.$token)->assertRedirect('/login');
        $this->assertDatabaseCount('enrollments', 0);
        $student = User::factory()->create();
        $this->signIn($student)->get('/invitations/'.$token)->assertOk();
        $this->assertDatabaseCount('enrollments', 0);
        $this->get('/student/courses/'.$offering->id)->assertForbidden();
        $this->post('/invitations/'.$token)->assertRedirect('/student/courses/'.$offering->id);
        $this->assertDatabaseCount('enrollments', 1);
        $invite->update(['revoked_at' => now()]);
        $this->post('/invitations/'.$token)->assertNotFound();
    }

    public function test_foreign_instructor_and_student_cannot_manage_records_or_view_rosters(): void
    {
        $offering = $this->offering();
        $other = $this->offering();
        $this->signIn($other->instructor)->get('/instructor/offerings/'.$offering->id)->assertForbidden();
        $this->post('/instructor/offerings/'.$offering->id.'/groups', ['name' => 'Hacked'])->assertForbidden();
        $this->signIn($offering->instructor)->put('/instructor/offerings/'.$offering->id.'/groups/'.$other->groups->first()->id, ['name' => 'Hacked'])->assertNotFound();
        $student = User::factory()->create();
        $this->signIn($student)->get('/instructor/offerings')->assertForbidden();
        $this->post('/instructor/setup/courses', ['name' => 'Hacked', 'code' => 'hack'])->assertForbidden();
    }

    public function test_capacity_cannot_shrink_below_enrollments_and_used_group_cannot_archive(): void
    {
        $offering = $this->offering();
        $student = User::factory()->create();
        $group = $offering->groups->first();
        app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $student, $group->id);
        $second = User::factory()->create();
        app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $second, $group->id);
        $this->signIn($offering->instructor)->put('/instructor/offerings/'.$offering->id.'/groups/'.$group->id, ['name' => 'Group A', 'capacity' => 1])->assertSessionHasErrors('capacity');
        $this->post('/instructor/offerings/'.$offering->id.'/groups/'.$group->id.'/archive')->assertSessionHasErrors('archive');
    }

    public function test_instructor_assisted_reset_and_suspension_are_scoped_and_audited(): void
    {
        $offering = $this->offering();
        $student = User::factory()->create();
        $enrollment = app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $student, $offering->groups->first()->id);
        $this->signIn(User::factory()->instructor()->create())->post('/instructor/enrollments/'.$enrollment->id.'/account', ['action' => 'reset', 'reason' => 'Identity checked'])->assertForbidden();
        $this->signIn($offering->instructor)->post('/instructor/enrollments/'.$enrollment->id.'/account', ['action' => 'reset', 'reason' => 'Identity checked in person'])->assertSessionHas('temporary_password');
        $this->assertTrue($student->fresh()->must_change_password);
        $this->assertSame(2, $student->fresh()->session_version);
        $this->post('/instructor/enrollments/'.$enrollment->id.'/account', ['action' => 'suspend', 'reason' => 'Account misuse'])->assertRedirect();
        $this->assertSame('suspended', $student->fresh()->status);
        $audit = AuditEvent::where('action', 'account_reset')->sole();
        $this->assertSame('Identity checked in person', $audit->reason);
        $this->assertSame([], $audit->changes);
    }

    public function test_closed_offering_rejects_enrollment_and_completion_closes_membership(): void
    {
        $offering = $this->offering();
        $student = User::factory()->create();
        $enrollment = app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $student, $offering->groups->first()->id);
        $this->signIn($offering->instructor)->post('/instructor/offerings/'.$offering->id.'/status', ['status' => 'completed', 'reason' => 'Semester ended'])->assertRedirect();
        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertSame(0, $enrollment->memberships()->whereNull('ends_at')->count());
        $newStudent = User::factory()->create();
        $this->signIn($offering->instructor)->post('/instructor/offerings/'.$offering->id.'/enrollments', ['identifier' => $newStudent->student_code, 'group_id' => $offering->groups->first()->id])->assertSessionHasErrors('enrollment');
    }

    public function test_completed_and_archived_offerings_cannot_reopen(): void
    {
        $offering = $this->offering();
        $this->signIn($offering->instructor)
            ->post('/instructor/offerings/'.$offering->id.'/status', ['status' => 'completed', 'reason' => 'Term ended'])
            ->assertRedirect();
        $this->post('/instructor/offerings/'.$offering->id.'/status', ['status' => 'open', 'reason' => 'Reopen'])
            ->assertSessionHasErrors('status');
        $this->post('/instructor/offerings/'.$offering->id.'/status', ['status' => 'archived', 'reason' => 'Retention'])
            ->assertRedirect();
        $this->post('/instructor/offerings/'.$offering->id.'/status', ['status' => 'open', 'reason' => 'Reopen'])
            ->assertSessionHasErrors('status');
    }
}
