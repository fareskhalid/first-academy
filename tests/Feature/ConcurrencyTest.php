<?php

namespace Tests\Feature;

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
}
