<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'must_change_password' => 'boolean', 'temporary_password_expires_at' => 'datetime'];
    }

    public function isInstructor(): bool
    {
        return $this->role === 'instructor';
    }

    public function profile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }
}
