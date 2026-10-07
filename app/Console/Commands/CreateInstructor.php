<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\PhoneNumber;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateInstructor extends Command
{
    protected $signature = 'instructor:create {--name=} {--phone=}';

    protected $description = 'Provision an instructor through trusted operator access (password entered privately)';

    public function handle(): int
    {
        $data = ['name' => $this->option('name') ?: $this->ask('Instructor name'), 'phone' => Phone::normalize($this->option('phone') ?: $this->ask('Egyptian mobile number')), 'password' => $this->secret('Password (8+ characters)')];
        $validator = Validator::make($data, ['name' => 'required|string|max:150', 'phone' => ['required', new PhoneNumber, 'unique:users,phone'], 'password' => ['required', Password::min(8), 'max:255']]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            $user = User::create($data + ['role' => 'instructor']);
            Audit::record($user, 'instructor_created', $user);
        });
        $this->info('Instructor created. Sign in using the supplied phone and password.');

        return self::SUCCESS;
    }
}
