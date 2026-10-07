<?php

namespace App\Policies;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AcademicPolicy
{
    public function manage(User $user, Model $model): bool
    {
        $owner = $model->instructor_id ?? $model->offering?->instructor_id;

        return $user->isInstructor() && $user->status === 'active' && (int) $owner === $user->id;
    }

    public function view(User $user, Model $model): bool
    {
        if ($this->manage($user, $model)) {
            return true;
        }
        if ($user->role !== 'student' || $user->status !== 'active') {
            return false;
        }
        if ($model instanceof Enrollment) {
            return $model->student_id === $user->id;
        }
        if ($model instanceof CourseOffering) {
            return $model->enrollments()->where('student_id', $user->id)->exists() || ($model->status === 'open' && $model->self_enrollment);
        }

        return false;
    }
}
