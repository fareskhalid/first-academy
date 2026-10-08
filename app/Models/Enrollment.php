<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function offering()
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function memberships()
    {
        return $this->hasMany(GroupMembership::class);
    }

    public function transferRequests()
    {
        return $this->hasMany(TransferRequest::class);
    }

    public function attendances() { return $this->hasMany(Attendance::class); }
    public function entitlements() { return $this->hasMany(CourseEntitlement::class); }
    public function accessWaivers() { return $this->hasMany(AccessWaiver::class); }
    public function rosterEntries() { return $this->hasMany(SessionRosterEntry::class); }
}
