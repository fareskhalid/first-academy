<?php

namespace App\Providers;

use App\Http\Middleware\ActiveAccount;
use App\Http\Middleware\InstructorOnly;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Semester;
use App\Policies\AcademicPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        foreach ([Course::class, CourseOffering::class, Enrollment::class, Group::class, Lesson::class, Semester::class] as $model) {
            Gate::policy($model, AcademicPolicy::class);
        }
        RateLimiter::for('login-ip', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('registration', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        Livewire::addPersistentMiddleware([ActiveAccount::class, InstructorOnly::class]);
    }
}
