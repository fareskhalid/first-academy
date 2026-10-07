<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Support\Audit;
use App\Support\Notices;
use Illuminate\Support\Facades\DB;

class RegisterStudent
{
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'phone' => $data['phone'], 'password' => $data['password'], 'role' => 'student', 'locale' => app()->getLocale()]);
            $user->update(['student_code' => 'STU-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT)]);
            $user->profile()->create(['whatsapp_phone' => $data['whatsapp_phone'], 'whatsapp_declared_at' => now(), 'university' => $data['university'] ?? null, 'university_student_id' => $data['university_student_id'] ?? null]);
            Audit::record($user, 'registered', $user);
            Notices::send($user, 'notice_welcome', ['name' => $user->name]);

            return $user;
        });
    }
}
