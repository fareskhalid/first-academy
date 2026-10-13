<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManageStudentAccount
{
    public function handle(User $actor, User $student, string $action, string $reason): ?string
    {
        abort_unless($actor->isInstructor() && $student->role === 'student', 403);
        abort_unless(in_array($action, ['suspend', 'reactivate', 'reset'], true), 422);
        abort_unless($this->isManagedBy($actor, $student), 403);

        $temporary = $action === 'reset' ? Str::password(16) : null;

        DB::transaction(function () use ($actor, $student, $action, $reason, $temporary) {
            $student = User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->isManagedBy($actor, $student), 403);

            $changes = [
                'session_version' => $student->session_version + 1,
                'remember_token' => Str::random(60),
            ];
            if ($temporary) {
                $changes += [
                    'password' => $temporary,
                    'must_change_password' => true,
                    'temporary_password_expires_at' => now()->addHour(),
                ];
            } else {
                $changes += ['status' => $action === 'suspend' ? 'suspended' : 'active'];
            }

            $student->update($changes);
            Audit::record($actor, 'account_'.$action, $student, [], $reason, $actor->id);
        });

        return $temporary;
    }

    private function isManagedBy(User $actor, User $student): bool
    {
        return $student->enrollments()
            ->whereHas('offering', fn ($query) => $query->where('instructor_id', $actor->id))
            ->exists();
    }
}
