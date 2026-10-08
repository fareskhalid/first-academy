<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDenial extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime'];
    }

    public function session()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
