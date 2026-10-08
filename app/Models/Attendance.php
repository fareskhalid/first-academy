<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['observed_at' => 'datetime', 'recorded_at' => 'datetime']; }
    public function enrollment() { return $this->belongsTo(Enrollment::class); }
    public function lesson() { return $this->belongsTo(Lesson::class); }
    public function session() { return $this->belongsTo(ClassSession::class, 'class_session_id'); }
    public function rosterEntry() { return $this->belongsTo(SessionRosterEntry::class, 'session_roster_entry_id'); }
    public function revisions() { return $this->hasMany(AttendanceRevision::class); }
}
