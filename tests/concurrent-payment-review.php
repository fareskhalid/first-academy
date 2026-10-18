<?php

use App\Models\PaymentProof;
use App\Models\User;
use App\Services\PaymentWorkflow;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.mysql.database') !== 'course_system_testing') {
    exit(9);
}
[, $proofId, $instructorId, $start] = $argv;
while (microtime(true) < (float) $start) {
    usleep(1000);
}

app(PaymentWorkflow::class)->review(
    User::findOrFail($instructorId),
    PaymentProof::findOrFail($proofId),
    'approved',
    'Concurrent verification.',
    100000,
);
