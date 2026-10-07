<?php

use App\Actions\Academics\EnrollmentManager;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.mysql.database') !== 'course_system_testing') {
    exit(9);
}
[$script,$offeringId,$studentId,$groupId,$start] = $argv;
while (microtime(true) < (float) $start) {
    usleep(1000);
}
try {
    $user = User::findOrFail($studentId);
    app(EnrollmentManager::class)->enroll($user, CourseOffering::findOrFail($offeringId), $user, (int) $groupId);
    exit(0);
} catch (ValidationException $e) {
    exit(2);
}
