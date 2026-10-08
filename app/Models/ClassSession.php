<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime', 'scheduled_end_at' => 'datetime',
            'attendance_opens_at' => 'datetime', 'attendance_closes_at' => 'datetime',
            'late_after_at' => 'datetime', 'published_at' => 'datetime', 'started_at' => 'datetime',
            'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
            'roster_captured_at' => 'datetime', 'absences_finalized_at' => 'datetime',
        ];
    }

    public function offering()
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function revisions()
    {
        return $this->hasMany(SessionScheduleRevision::class);
    }

    public function rosterEntries()
    {
        return $this->hasMany(SessionRosterEntry::class);
    }

    public function qrCredentials()
    {
        return $this->hasMany(QrCredential::class);
    }

    public function intents()
    {
        return $this->hasMany(AttendanceIntent::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function authorizations()
    {
        return $this->hasMany(AttendanceAuthorization::class);
    }
}
