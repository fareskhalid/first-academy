<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceAuthorization extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['revoked_at' => 'datetime']; }
    public function session() { return $this->belongsTo(ClassSession::class, 'class_session_id'); }
    public function enrollment() { return $this->belongsTo(Enrollment::class); }
}
