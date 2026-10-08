<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionRosterEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime'];
    }

    public function session()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function attendance()
    {
        return $this->hasOne(Attendance::class);
    }
}
