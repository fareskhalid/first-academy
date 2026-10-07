<?php

namespace Tests;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.connections.mysql.database') !== 'course_system_testing' || ! $app->environment('testing')) {
            throw new \RuntimeException('Refusing to test against a non-isolated database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth_version' => $user->session_version]);
    }

    protected function offering(?User $teacher = null, array $attributes = []): CourseOffering
    {
        $teacher ??= User::factory()->instructor()->create();
        $course = Course::create(['instructor_id' => $teacher->id, 'name' => 'Computer science', 'code' => 'CS'.fake()->unique()->numberBetween(1, 99999)]);
        $semester = Semester::create(['instructor_id' => $teacher->id, 'name' => 'Autumn', 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
        $offering = CourseOffering::create($attributes + ['instructor_id' => $teacher->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'title' => 'Algorithms', 'fee_minor' => 150000, 'currency' => 'EGP', 'timezone' => 'Africa/Cairo', 'uses_groups' => true, 'status' => 'open', 'self_enrollment' => true]);
        $offering->groups()->createMany([['name' => 'Group A', 'capacity' => 2], ['name' => 'Group B', 'capacity' => 2]]);

        return $offering;
    }
}
