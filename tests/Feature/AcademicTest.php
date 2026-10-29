<?php

namespace Tests\Feature;

use App\Actions\Academics\EnrollmentManager;
use App\Models\AttendanceIntent;
use App\Models\AuditEvent;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\EnrollmentInvitation;
use App\Models\Semester;
use App\Models\User;
use App\Services\QrCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_scanner_is_available_only_to_signed_in_students(): void
    {
        $this->get('/student/attendance/scanner')->assertRedirect('/login');

        $student = User::factory()->create();
        $this->signIn($student)
            ->get('/student/attendance/scanner')
            ->assertOk()
            ->assertSee('data-qr-scanner', false)
            ->assertSee(route('attendance.code'), false);

        $this->signIn(User::factory()->instructor()->create())
            ->get('/student/attendance/scanner')
            ->assertForbidden();
    }

    public function test_instructor_creates_semester_course_offering_groups_and_lessons(): void
    {
        $teacher = User::factory()->instructor()->create();
        $this->signIn($teacher);
        $this->post('/instructor/setup/semesters', ['name' => 'Autumn', 'name_ar' => 'الخريف', 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31'])->assertRedirect();
        $this->post('/instructor/setup/courses', ['name' => 'Programming', 'name_ar' => 'البرمجة', 'code' => 'CS101', 'description' => 'Course', 'description_ar' => 'كورس'])->assertRedirect();
        $courseId = Course::sole()->id;
        $semesterId = Semester::sole()->id;
        $this->post('/instructor/offerings', ['title' => 'Programming Autumn', 'title_ar' => 'برمجة الخريف', 'course_id' => $courseId, 'semester_id' => $semesterId, 'uses_groups' => 1])->assertRedirect();
        $offering = CourseOffering::sole();
        $this->assertSame('draft', $offering->status);
        foreach ([['Group A', 'المجموعة أ'], ['Group B', 'المجموعة ب']] as [$name, $nameAr]) {
            $this->post('/instructor/offerings/'.$offering->id.'/groups', ['name' => $name, 'name_ar' => $nameAr, 'capacity' => 20])->assertRedirect();
        }
        $this->post('/instructor/offerings/'.$offering->id.'/lessons', ['title' => 'Introduction', 'title_ar' => 'مقدمة', 'position' => 1])->assertRedirect();
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
        $this->post('/instructor/offerings', ['title' => 'General offering', 'title_ar' => 'كورس عام', 'course_id' => $existing->course_id, 'semester_id' => $existing->semester_id])->assertRedirect();
        $general = CourseOffering::latest('id')->first();
        $this->assertFalse($general->uses_groups);
        $this->assertSame('General', $general->groups()->sole()->name);
        $this->post('/instructor/offerings/'.$general->id.'/groups', ['name' => 'Another', 'name_ar' => 'أخرى'])->assertSessionHasErrors('name');
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

    public function test_invitation_detects_existing_and_new_accounts_before_acceptance(): void
    {
        $offering = $this->offering();
        $token = Str::random(64);
        $invite = EnrollmentInvitation::create(['course_offering_id' => $offering->id, 'group_id' => $offering->groups->first()->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);
        $this->get('/invitations/'.$token)->assertOk()->assertSee('Algorithms');
        $this->assertDatabaseCount('enrollments', 0);
        $student = User::factory()->create();
        $this->post('/invitations/'.$token.'/account', ['phone' => $student->phone])
            ->assertRedirect(route('login', ['identifier' => $student->phone]));
        $this->assertSame(route('invitations.show', $token), session('url.intended'));
        $newPhone = '+201155555555';
        $this->post('/invitations/'.$token.'/account', ['phone' => $newPhone])
            ->assertRedirect(route('register', ['phone' => $newPhone]));
        $this->post('/register', [
            'name' => 'Invited Student',
            'phone' => $newPhone,
            'password' => 'StudentPassword123!',
            'password_confirmation' => 'StudentPassword123!',
        ])->assertRedirect('/login');
        $this->post('/login', ['identifier' => $newPhone, 'password' => 'StudentPassword123!'])
            ->assertRedirect(route('invitations.show', $token));
        $this->get('/invitations/'.$token)->assertOk();
        $this->assertDatabaseCount('enrollments', 0);
        $this->get('/student/courses/'.$offering->id)->assertForbidden();
        $this->post('/invitations/'.$token)->assertRedirect('/student/courses/'.$offering->id);
        $this->assertDatabaseCount('enrollments', 1);
        $invite->update(['revoked_at' => now()]);
        $this->post('/invitations/'.$token)->assertNotFound();
    }

    public function test_instructor_can_reopen_active_invitation_links_and_regenerate_legacy_links(): void
    {
        $offering = $this->offering();
        $this->signIn($offering->instructor)
            ->post('/instructor/offerings/'.$offering->id.'/invitations', ['group_id' => $offering->groups->first()->id])
            ->assertRedirect()
            ->assertSessionHas('invitation_url');

        $invitation = EnrollmentInvitation::sole();
        $url = route('invitations.show', $invitation->token);
        $this->assertSame($url, $invitation->invitation_url);
        $this->get('/instructor/offerings/'.$offering->id)
            ->assertOk()
            ->assertSee($url);

        $legacy = EnrollmentInvitation::create([
            'course_offering_id' => $offering->id,
            'group_id' => $offering->groups->last()->id,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDay(),
        ]);
        $this->post('/instructor/invitations/'.$legacy->id.'/refresh-link')
            ->assertRedirect()
            ->assertSessionHas('invitation_url');
        $this->assertNotNull($legacy->fresh()->token);
    }

    public function test_offering_roster_supports_scoped_search_filters_and_sorting(): void
    {
        $offering = $this->offering();
        $amy = User::factory()->create(['name' => 'Amy Active']);
        $zed = User::factory()->create(['name' => 'Zed Withdrawn']);
        app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $amy, $offering->groups->first()->id);
        $zedEnrollment = app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $zed, $offering->groups->last()->id);
        $zedEnrollment->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);

        $this->signIn($offering->instructor)
            ->get('/instructor/offerings/'.$offering->id.'?student_search=Amy&student_status=enrolled&student_group='.$offering->groups->first()->id.'&student_sort=name_desc')
            ->assertOk()
            ->assertSee('Amy Active')
            ->assertDontSee('Zed Withdrawn');
        $this->get('/instructor/offerings/'.$offering->id.'?student_sort=name_desc')
            ->assertOk()
            ->assertSeeInOrder(['Zed Withdrawn', 'Amy Active']);
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
        $this->signIn($offering->instructor)->put('/instructor/offerings/'.$offering->id.'/groups/'.$group->id, ['name' => 'Group A', 'name_ar' => 'المجموعة أ', 'capacity' => 1])->assertSessionHasErrors('capacity');
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

    public function test_student_management_lists_only_the_instructors_students_and_scopes_actions(): void
    {
        $offering = $this->offering();
        $managed = User::factory()->create(['name' => 'Managed Student']);
        app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $managed, $offering->groups->first()->id);

        $foreignOffering = $this->offering();
        $foreign = User::factory()->create(['name' => 'Foreign Student']);
        app(EnrollmentManager::class)->enroll($foreignOffering->instructor, $foreignOffering, $foreign, $foreignOffering->groups->first()->id);

        $this->signIn($offering->instructor)
            ->get('/instructor/students?search=Managed')
            ->assertOk()
            ->assertSee('Managed Student')
            ->assertDontSee('Foreign Student')
            ->assertDontSee('Algorithms')
            ->assertSee('Student directory')
            ->assertSee('Joined on');
        $this->post('/instructor/students/'.$foreign->id.'/account', ['action' => 'suspend', 'reason' => 'Outside scope'])->assertForbidden();
        $this->post('/instructor/students/'.$managed->id.'/account', ['action' => 'suspend', 'reason' => 'Account review'])->assertRedirect();
        $this->assertSame('suspended', $managed->fresh()->status);
    }

    public function test_instructor_manages_scoped_lectures_with_new_attendance_defaults(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $offering = $this->offering();
        $lesson = $offering->lessons()->create(['title' => 'Own Lecture', 'title_ar' => 'محاضرتي', 'position' => 1]);

        $this->signIn($offering->instructor)->post('/instructor/schedule/sessions', [
            'course_offering_id' => $offering->id,
            'group_id' => $offering->groups->first()->id,
            'lesson_id' => $lesson->id,
            'scheduled_start' => '2026-10-17T10:00',
            'scheduled_end' => '2026-10-17T12:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $session = ClassSession::sole();
        $this->assertSame('2026-10-17 10:00', $session->attendance_opens_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-17 11:00', $session->late_after_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-17 12:00', $session->attendance_closes_at->format('Y-m-d H:i'));
        $this->assertNull($session->location);

        $this->post('/instructor/schedule/publish', [
            'session_ids' => [$session->id],
            'week' => '2026-10-17',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->travelTo('2026-10-17 09:59:59');
        $this->assertSame('published', $session->fresh()->display_status);

        $this->travelTo('2026-10-17 10:00:00');
        $this->assertSame('in_progress', $session->fresh()->display_status);
        $this->assertSame(1, ClassSession::query()->withDisplayStatus('in_progress')->count());
        $this->get('/instructor/lectures?status=in_progress')
            ->assertOk()
            ->assertSee('Own Lecture')
            ->assertSee('In progress');

        $this->travelTo('2026-10-17 12:00:00');
        $this->assertSame('ended', $session->fresh()->display_status);
        $this->assertSame(1, ClassSession::query()->withDisplayStatus('ended')->count());

        $foreignOffering = $this->offering();
        $foreignLesson = $foreignOffering->lessons()->create(['title' => 'Foreign Lecture', 'title_ar' => 'محاضرة أخرى', 'position' => 1]);
        $this->signIn($foreignOffering->instructor)->post('/instructor/schedule/sessions', [
            'course_offering_id' => $foreignOffering->id,
            'group_id' => $foreignOffering->groups->first()->id,
            'lesson_id' => $foreignLesson->id,
            'scheduled_start' => '2026-10-18T10:00',
            'scheduled_end' => '2026-10-18T12:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->signIn($offering->instructor)
            ->get('/instructor/lectures?search=Own')
            ->assertOk()
            ->assertSee('Own Lecture')
            ->assertDontSee('Foreign Lecture');

        $this->travelTo('2026-10-19 10:00:00');
        $this->assertSame('ended', $session->fresh()->display_status);
        $this->get('/instructor/lectures?status=ended&search=Own')
            ->assertOk()
            ->assertSee('Own Lecture')
            ->assertSee('Ended')
            ->assertDontSee('Foreign Lecture');
        $this->get('/instructor/schedule')
            ->assertOk()
            ->assertDontSee('name="location"', false);
    }

    public function test_student_records_attendance_in_one_step_with_short_code(): void
    {
        [$student, $session, $issued] = $this->attendanceScenario();
        $issued['shortCode'] = 'A1B2C3';
        $issued['credential']->update(['short_code_hash' => hash('sha256', $issued['shortCode'])]);

        $response = $this->signIn($student)->post('/attendance/code', ['code' => strtolower($issued['shortCode'])]);

        $intent = AttendanceIntent::sole();
        $response->assertRedirect(route('attendance.result', $intent));
        $this->assertTrue(Str::isUuid($intent->id));
        $this->assertSame($student->id, $intent->user_id);
        $this->assertSame($session->id, $intent->class_session_id);
        $this->assertSame('code', $intent->method);
        $this->assertNotNull($intent->consumed_at);
        $this->assertDatabaseHas('attendances', [
            'class_session_id' => $session->id,
            'status' => 'present',
            'method' => 'code',
        ]);
    }

    public function test_student_records_attendance_with_one_click_after_scanning_qr(): void
    {
        [$student, $session, $issued] = $this->attendanceScenario();
        $this->signIn($student)
            ->get(route('attendance.scan', $issued['token']))
            ->assertOk()
            ->assertSee('Record attendance')
            ->assertDontSee('Continue check-in');

        $response = $this->post(route('attendance.start', $issued['token']));

        $intent = AttendanceIntent::sole();
        $response->assertRedirect(route('attendance.result', $intent));
        $this->assertSame('qr', $intent->method);
        $this->assertNotNull($intent->consumed_at);
        $this->assertDatabaseHas('attendances', [
            'class_session_id' => $session->id,
            'status' => 'present',
            'method' => 'qr',
        ]);
    }

    public function test_repeated_attendance_submission_is_idempotent(): void
    {
        [$student, $session, $issued] = $this->attendanceScenario();
        $this->signIn($student);

        $this->post('/attendance/code', ['code' => $issued['shortCode']])->assertRedirect();
        $firstIntent = AttendanceIntent::sole();
        $second = $this->post('/attendance/code', ['code' => $issued['shortCode']]);

        $secondIntent = AttendanceIntent::whereKeyNot($firstIntent->id)->sole();
        $second->assertRedirect(route('attendance.result', $secondIntent));
        $second->assertSessionHas('attendance_duplicate', true);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertSame($session->id, $secondIntent->class_session_id);
    }

    public function test_my_lectures_lists_only_sessions_assigned_to_the_student(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $offering = $this->offering();
        $student = User::factory()->create();
        $enrollment = app(EnrollmentManager::class)->enroll(
            $offering->instructor,
            $offering,
            $student,
            $offering->groups->first()->id,
        );
        $lesson = $offering->lessons()->create(['title' => 'My Assigned Lecture', 'title_ar' => 'محاضرتي المخصصة', 'position' => 1]);
        ClassSession::create([
            'course_offering_id' => $offering->id,
            'group_id' => $enrollment->group_id,
            'lesson_id' => $lesson->id,
            'scheduled_start_at' => '2026-10-17 10:00:00',
            'scheduled_end_at' => '2026-10-17 12:00:00',
            'attendance_opens_at' => '2026-10-17 10:00:00',
            'attendance_closes_at' => '2026-10-17 12:00:00',
            'late_after_at' => '2026-10-17 11:00:00',
            'status' => 'published',
        ]);
        $hiddenLesson = $offering->lessons()->create(['title' => 'Other Group Lecture', 'title_ar' => 'محاضرة مجموعة أخرى', 'position' => 2]);
        ClassSession::create([
            'course_offering_id' => $offering->id,
            'group_id' => $offering->groups->last()->id,
            'lesson_id' => $hiddenLesson->id,
            'scheduled_start_at' => '2026-10-18 10:00:00',
            'scheduled_end_at' => '2026-10-18 12:00:00',
            'attendance_opens_at' => '2026-10-18 10:00:00',
            'attendance_closes_at' => '2026-10-18 12:00:00',
            'late_after_at' => '2026-10-18 11:00:00',
            'status' => 'published',
        ]);

        $this->signIn($student)
            ->get('/student/lectures')
            ->assertOk()
            ->assertSee('My Assigned Lecture')
            ->assertDontSee('Other Group Lecture');
        $this->get('/student/lectures?period=upcoming')
            ->assertOk()
            ->assertSee('My Assigned Lecture');
        $this->get('/student/lectures?period=past')
            ->assertOk()
            ->assertDontSee('My Assigned Lecture');
        $this->signIn($offering->instructor)->get('/student/lectures')->assertForbidden();
    }

    public function test_arabic_student_interface_uses_translated_academic_content(): void
    {
        $offering = $this->offering();
        $offering->course->update(['description' => 'English description', 'description_ar' => 'وصف عربي']);
        $student = User::factory()->create(['locale' => 'ar']);
        app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $student, $offering->groups->first()->id);

        $this->signIn($student)
            ->get('/student/courses/'.$offering->id)
            ->assertOk()
            ->assertSee('الخوارزميات')
            ->assertSee('<p>وصف عربي</p>', false);
    }

    public function test_academic_timezone_is_always_cairo_without_database_columns(): void
    {
        $offering = $this->offering();

        $this->assertSame('Africa/Cairo', config('app.timezone'));
        $this->assertSame('Africa/Cairo', config('database.timezone'));
        $this->assertSame('Africa/Cairo', $offering->timezone);
        $this->assertFalse(Schema::hasColumn('course_offerings', 'timezone'));
        $this->assertFalse(Schema::hasColumn('class_sessions', 'timezone'));
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

    private function attendanceScenario(): array
    {
        $this->travelTo('2026-10-17 10:30:00');
        $offering = $this->offering();
        $lesson = $offering->lessons()->create(['title' => 'Attendance Lecture', 'title_ar' => 'محاضرة الحضور', 'position' => 1]);
        $student = User::factory()->create();
        $enrollment = app(EnrollmentManager::class)->enroll(
            $offering->instructor,
            $offering,
            $student,
            $offering->groups->first()->id,
        );
        $session = ClassSession::create([
            'course_offering_id' => $offering->id,
            'group_id' => $enrollment->group_id,
            'lesson_id' => $lesson->id,
            'scheduled_start_at' => now()->subMinutes(30),
            'scheduled_end_at' => now()->addMinutes(90),
            'attendance_opens_at' => now()->subMinutes(30),
            'attendance_closes_at' => now()->addMinutes(90),
            'late_after_at' => now()->addMinutes(30),
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        return [$student, $session, app(QrCredentialService::class)->issue($session)];
    }
}
