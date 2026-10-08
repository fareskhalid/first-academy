<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceIntent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id'];

    protected $hidden = ['browser_session_hash'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'expires_at' => 'datetime', 'consumed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function session()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function credential()
    {
        return $this->belongsTo(QrCredential::class, 'qr_credential_id');
    }
}
