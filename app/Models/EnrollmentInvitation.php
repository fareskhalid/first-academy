<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrollmentInvitation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function getInvitationUrlAttribute(): ?string
    {
        return $this->token ? route('invitations.show', $this->token) : null;
    }

    public function offering()
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
