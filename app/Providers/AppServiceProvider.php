<?php

namespace App\Providers;

use App\Contracts\CourseAccess;
use App\Http\Middleware\ActiveAccount;
use App\Http\Middleware\InstructorOnly;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Semester;
use App\Policies\AcademicPolicy;
use App\Services\CourseAccessService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CourseAccess::class, CourseAccessService::class);
    }

    public function boot(): void
    {
        foreach ([Course::class, CourseOffering::class, Enrollment::class, Group::class, Lesson::class, Semester::class, ClassSession::class] as $model) {
            Gate::policy($model, AcademicPolicy::class);
        }
        RateLimiter::for('login-ip', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('registration', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('attendance-code', fn (Request $r) => Limit::perMinute(10)->by('attendance-code:'.$r->user()?->id));
        Livewire::addPersistentMiddleware([ActiveAccount::class, InstructorOnly::class]);
    }
}
