<?php

namespace App\Policies;

use App\Models\CourseMaterial;
use App\Models\User;

class CourseMaterialPolicy
{
    public function view(User $user, CourseMaterial $material): bool
    {
        if ($this->manage($user, $material)) {
            return true;
        }

        if ($user->role !== 'student' || $user->status !== 'active' || $material->status !== 'published' || $material->archived_at) {
            return false;
        }

        return $material->offering->enrollments()
            ->where('student_id', $user->id)
            ->where('status', 'enrolled')
            ->whereIn('group_id', $material->groups()->select('groups.id'))
            ->exists();
    }

    public function manage(User $user, CourseMaterial $material): bool
    {
        return $user->isInstructor()
            && $user->status === 'active'
            && $material->offering->instructor_id === $user->id;
    }
}
