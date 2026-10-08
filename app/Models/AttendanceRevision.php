<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRevision extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array { return ['before_values' => 'array', 'after_values' => 'array', 'created_at' => 'datetime']; }
    public function attendance() { return $this->belongsTo(Attendance::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
