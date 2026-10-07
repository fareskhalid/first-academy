<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResetAccount extends Command
{
    protected $signature = 'account:reset {identifier}';

    protected $description = 'Trusted operator recovery after an in-person identity check; expires in one hour';

    public function handle(): int
    {
        $identifier = Phone::identifier($this->argument('identifier'));
        $user = User::where(str_starts_with($identifier, 'STU-') ? 'student_code' : 'phone', $identifier)->first();
        if (! $user) {
            $this->error('Account not found.');

            return self::FAILURE;
        }
        if (! $this->confirm('Identity checked in person for '.$user->name.'? Existing sessions will end.')) {
            return self::FAILURE;
        }
        $reason = $this->ask('Reason for recovery');
        if (! $reason || mb_strlen($reason) > 500) {
            $this->error('A reason of 1–500 characters is required.');

            return self::FAILURE;
        }
        $password = Str::password(16);
        DB::transaction(function () use ($user, $reason, $password) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $user->update(['password' => $password, 'must_change_password' => true, 'temporary_password_expires_at' => now()->addHour(), 'session_version' => $user->session_version + 1, 'remember_token' => Str::random(60)]);
            Audit::record(null, 'account_reset', $user, [], $reason, $user->isInstructor() ? $user->id : null);
        });
        $this->line('Temporary password (share privately): '.$password);

        return self::SUCCESS;
    }
}
