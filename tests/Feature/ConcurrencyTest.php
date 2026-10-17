<?php

namespace Tests\Feature;

use App\Actions\Academics\EnrollmentManager;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_enrollment_cannot_oversubscribe_last_seat(): void
    {
        $offering = $this->offering();
        $group = $offering->groups->first();
        $group->update(['capacity' => 1]);
        $students = User::factory()->count(2)->create();
        $start = (string) (microtime(true) + 1.5);
        $processes = $students->map(fn ($student) => new Process([PHP_BINARY, base_path('tests/concurrent-enroll.php'), (string) $offering->id, (string) $offering->instructor_id, (string) $student->id, (string) $group->id, $start], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'course_system_testing', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array']));
        foreach ($processes as $process) {
            $process->start();
        }
        $codes = [];
        foreach ($processes as $process) {
            $codes[] = $process->wait();
            $this->assertSame('', $process->getErrorOutput());
        }
        sort($codes);
        $this->assertSame([0, 2], $codes);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('group_memberships', 1);
    }

    public function test_concurrent_payment_approval_creates_one_entitlement_and_review(): void
    {
        $offering = $this->offering();
        $student = User::factory()->create();
        $enrollment = app(EnrollmentManager::class)->enroll($offering->instructor, $offering, $student, $offering->groups->first()->id);
        $proof = PaymentProof::create([
            'enrollment_id' => $enrollment->id,
            'file_disk' => 'local',
            'file_path' => 'payment-proofs/test/concurrent.pdf',
            'original_name' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'sha256' => hash('sha256', 'concurrent-proof'),
            'claimed_amount_minor' => 100000,
            'currency' => 'EGP',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Wallet',
            'status' => 'pending',
            'pending_slot' => 1,
        ]);
        $start = (string) (microtime(true) + 1.5);
        $processes = collect([1, 2])->map(fn () => new Process([
            PHP_BINARY, base_path('tests/concurrent-payment-review.php'), (string) $proof->id,
            (string) $offering->instructor_id, $start,
        ], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'course_system_testing', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array']));
        foreach ($processes as $process) {
            $process->start();
        }
        foreach ($processes as $process) {
            $this->assertSame(0, $process->wait());
            $this->assertSame('', $process->getErrorOutput());
        }
        $this->assertSame('approved', $proof->fresh()->status);
        $this->assertDatabaseCount('course_entitlements', 1);
        $this->assertDatabaseCount('payment_reviews', 1);
    }
}
