<?php

use App\Models\User;

return [
    'defaults' => ['guard' => 'web'],
    'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']],
    'providers' => ['users' => ['driver' => 'eloquent', 'model' => User::class]],
    // Recovery is instructor/operator assisted. There is no email reset broker.
    'passwords' => [],
    'password_timeout' => 10800,
];
