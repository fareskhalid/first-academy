<?php

namespace App\Services;

use App\Contracts\CourseAccess;
use App\Models\Enrollment;
use Carbon\CarbonInterface;

class CourseAccessService implements CourseAccess
{
    public function attendedCount(Enrollment $enrollment): int
    {
        return $enrollment->attendances()->whereIn('status', ['present', 'late'])->distinct()->count('lesson_id');
    }

    public function source(Enrollment $enrollment, CarbonInterface $at): ?string
    {
        if ($this->attendedCount($enrollment) < (int) config('academic.free_attended_lessons', 2)) {
            return 'free';
        }

        if ($enrollment->entitlements()->whereNull('revoked_at')->where('valid_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>', $at))->exists()) {
            return 'entitlement';
        }

        if ($enrollment->accessWaivers()->whereNull('revoked_at')->where('scope', 'attendance')->where('valid_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>', $at))->exists()) {
            return 'waiver';
        }

        return null;
    }
}
