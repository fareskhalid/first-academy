<?php

namespace Tests\Feature;

use App\Actions\Academics\EnrollmentManager;
use App\Contracts\CourseAccess;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\CourseEntitlement;
use App\Models\Enrollment;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_submits_private_proof_and_only_owners_can_read_it(): void
    {
        Storage::fake('local');
        $offering = $this->offering();
        $student = User::factory()->create();
        $enrollment = $this->enroll($offering, $student);
        $this->instructions($offering);

        $this->signIn($student)->post(route('student.payments.store', $enrollment), $this->proofPayload())
            ->assertRedirect(route('student.payments.index'))
            ->assertSessionHasNoErrors();

        $proof = PaymentProof::sole();
        $this->assertSame('pending', $proof->status);
        $this->assertSame(1, $proof->pending_slot);
        $this->assertSame(125050, $proof->claimed_amount_minor);
        Storage::disk('local')->assertExists($proof->file_path);
        $this->get(route('student.payments.index'))->assertOk()->assertSee('Proof under review');
        $this->get(route('payment-proofs.file', $proof))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->signIn(User::factory()->create())->get(route('payment-proofs.file', $proof))->assertForbidden();
        $this->signIn(User::factory()->instructor()->create())->get(route('payment-proofs.file', $proof))->assertForbidden();
        $this->signIn($offering->instructor)->get(route('instructor.payments.index'))->assertOk()->assertSee($student->name);
        $this->get(route('payment-proofs.file', $proof))->assertOk();
    }

    public function test_approval_is_idempotent_and_unlocks_only_its_enrollment(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->instructor()->create();
        $first = $this->offering($teacher);
        $second = $this->offering($teacher);
        $student = User::factory()->create();
        $firstEnrollment = $this->enroll($first, $student);
        $secondEnrollment = $this->enroll($second, $student);
        $this->consumeFreeAttendances($firstEnrollment);
        $this->consumeFreeAttendances($secondEnrollment);

        $this->signIn($student)->post(route('student.payments.store', $firstEnrollment), $this->proofPayload())->assertRedirect();
        $proof = PaymentProof::sole();
        $payload = ['decision' => 'approved', 'reason' => 'Matched external payment records.', 'confirmed_amount' => '1250.50', 'confirmed_currency' => 'EGP'];
        $this->signIn($teacher)->post(route('instructor.payments.review', $proof), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('instructor.payments.review', $proof), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('approved', $proof->fresh()->status);
        $this->assertDatabaseCount('course_entitlements', 1);
        $this->assertDatabaseCount('payment_reviews', 1);
        $this->assertSame(125050, $proof->fresh()->confirmed_amount_minor);
        $this->assertSame('entitlement', app(CourseAccess::class)->source($firstEnrollment, now()));
        $this->assertNull(app(CourseAccess::class)->source($secondEnrollment, now()));
    }

    public function test_rejection_retains_history_and_allows_one_new_pending_submission(): void
    {
        Storage::fake('local');
        $offering = $this->offering();
        $student = User::factory()->create();
        $enrollment = $this->enroll($offering, $student);

        $this->signIn($student)->post(route('student.payments.store', $enrollment), $this->proofPayload())->assertRedirect();
        $first = PaymentProof::sole();
        $this->signIn($offering->instructor)->post(route('instructor.payments.review', $first), [
            'decision' => 'rejected', 'reason' => 'Reference could not be matched.',
        ])->assertRedirect();

        $this->signIn($student)->post(route('student.payments.store', $enrollment), $this->proofPayload(['payment_reference' => 'REF-002']))->assertRedirect()->assertSessionHasNoErrors();
        $second = PaymentProof::latest('id')->first();
        $this->assertSame($first->id, $second->supersedes_id);
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertSame('pending', $second->status);
        $this->post(route('student.payments.store', $enrollment), $this->proofPayload(['payment_reference' => 'REF-003']))->assertSessionHasErrors('proof');
        $this->assertDatabaseCount('payment_proofs', 2);
    }

    public function test_revocation_and_expired_waiver_change_future_access_only(): void
    {
        Storage::fake('local');
        $offering = $this->offering();
        $student = User::factory()->create();
        $enrollment = $this->enroll($offering, $student);
        $this->consumeFreeAttendances($enrollment);
        $this->signIn($student)->post(route('student.payments.store', $enrollment), $this->proofPayload())->assertRedirect();
        $proof = PaymentProof::sole();
        $this->signIn($offering->instructor)->post(route('instructor.payments.review', $proof), ['decision' => 'approved', 'reason' => 'Verified.', 'confirmed_amount' => '1250.50', 'confirmed_currency' => 'EGP'])->assertRedirect();
        $this->assertSame('entitlement', app(CourseAccess::class)->source($enrollment, now()));

        $this->post(route('instructor.payments.revoke', $proof), ['reason' => 'External payment was reversed.'])->assertRedirect();
        $this->assertNull(app(CourseAccess::class)->source($enrollment, now()));
        $this->assertNotNull(CourseEntitlement::sole()->revoked_at);
        $this->assertDatabaseCount('payment_reviews', 2);
        $this->assertDatabaseCount('attendances', 2);

        $this->post(route('instructor.waivers.store', $enrollment), ['valid_until' => now()->addHour()->format('Y-m-d H:i:s'), 'reason' => 'Temporary resolution window.'])->assertRedirect();
        $this->assertSame('waiver', app(CourseAccess::class)->source($enrollment, now()));
        $this->travel(61)->minutes();
        $this->assertNull(app(CourseAccess::class)->source($enrollment, now()));
        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_unsupported_or_oversized_files_do_not_create_proofs(): void
    {
        Storage::fake('local');
        $offering = $this->offering();
        $student = User::factory()->create();
        $enrollment = $this->enroll($offering, $student);
        $bad = $this->proofPayload(['proof' => UploadedFile::fake()->create('receipt.exe', 20, 'application/x-msdownload')]);
        $this->signIn($student)->post(route('student.payments.store', $enrollment), $bad)->assertSessionHasErrors('proof');
        $large = $this->proofPayload(['proof' => UploadedFile::fake()->create('receipt.pdf', 10241, 'application/pdf')]);
        $this->post(route('student.payments.store', $enrollment), $large)->assertSessionHasErrors('proof');
        $this->assertDatabaseCount('payment_proofs', 0);
    }

    private function enroll($offering, User $student): Enrollment
    {
        return app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $student, $offering->groups->first()->id);
    }

    private function instructions($offering): void
    {
        $offering->paymentInstruction()->create([
            'instructions' => 'Transfer using the supplied reference.',
            'instructions_ar' => 'حوّل باستخدام المرجع المطلوب.',
        ]);
    }

    private function proofPayload(array $overrides = []): array
    {
        return $overrides + [
            'proof' => UploadedFile::fake()->image('receipt.jpg', 600, 900),
            'claimed_amount' => '1250.50',
            'currency' => 'EGP',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Wallet transfer',
            'payment_reference' => 'REF-001',
        ];
    }

    private function consumeFreeAttendances(Enrollment $enrollment): void
    {
        foreach ([1, 2] as $position) {
            $lesson = $enrollment->offering->lessons()->create(['title' => 'Lesson '.$position, 'title_ar' => 'درس '.$position, 'position' => $position]);
            $start = now()->subDays(3 - $position)->startOfHour();
            $session = ClassSession::create([
                'course_offering_id' => $enrollment->course_offering_id,
                'group_id' => $enrollment->group_id,
                'lesson_id' => $lesson->id,
                'timezone' => config('academic.timezone'),
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $start->copy()->addHours(2),
                'attendance_opens_at' => $start,
                'attendance_closes_at' => $start->copy()->addHours(2),
                'late_after_at' => $start->copy()->addHour(),
                'status' => 'completed',
            ]);
            Attendance::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'class_session_id' => $session->id,
                'status' => 'present',
                'method' => 'instructor',
                'access_source' => 'free',
                'observed_at' => $start,
                'recorded_at' => $start,
            ]);
        }
    }
}
