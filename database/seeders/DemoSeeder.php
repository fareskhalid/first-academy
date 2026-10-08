<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Synthetic demo data is allowed only in local/testing environments.');
        }
        $password = getenv('DEMO_PASSWORD');
        if (! $password || strlen($password) < 12) {
            throw new \RuntimeException('Supply DEMO_PASSWORD with at least 12 characters.');
        }
        DB::transaction(function () use ($password) {
            $teacher = User::firstOrCreate(['phone' => '+201000000001'], ['name' => 'Demo Instructor', 'password' => $password, 'role' => 'instructor']);
            if (! $teacher->isInstructor()) {
                throw new \RuntimeException('Demo phone conflicts with an existing student.');
            }
            $semester = Semester::firstOrCreate(['instructor_id' => $teacher->id, 'name' => 'Demo semester'], ['starts_on' => now()->startOfMonth()->toDateString(), 'ends_on' => now()->addMonths(4)->endOfMonth()->toDateString()]);
            foreach (['CS101' => 'Programming fundamentals', 'CS102' => 'Data structures'] as $code => $title) {
                $course = Course::firstOrCreate(['instructor_id' => $teacher->id, 'code' => $code], ['name' => $title, 'description' => 'Synthetic course for exploring the system.']);
                $offering = CourseOffering::firstOrCreate(['instructor_id' => $teacher->id, 'course_id' => $course->id, 'semester_id' => $semester->id], ['title' => $title, 'timezone' => 'Africa/Cairo', 'status' => 'open', 'uses_groups' => true]);
                foreach (['Group A', 'Group B'] as $name) {
                    $offering->groups()->firstOrCreate(['name' => $name], ['capacity' => 30]);
                }
                foreach (['Introduction', 'Core concepts', 'Practice'] as $i => $title) {
                    $offering->lessons()->firstOrCreate(['position' => $i + 1], ['title' => $title]);
                }
            }
        });
        $this->command?->info('Demo instructor phone: 01000000001. Existing accounts/passwords were not overwritten.');
    }
}
