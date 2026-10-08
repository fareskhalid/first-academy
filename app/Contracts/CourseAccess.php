<?php

namespace App\Contracts;

use App\Models\Enrollment;
use Carbon\CarbonInterface;

interface CourseAccess
{
    public function attendedCount(Enrollment $enrollment): int;

    public function source(Enrollment $enrollment, CarbonInterface $at): ?string;
}
