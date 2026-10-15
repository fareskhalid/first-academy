<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function getTimezoneAttribute(): string
    {
        return config('academic.timezone');
    }

    public function getHasEndedAttribute(): bool
    {
        return $this->scheduled_end_at?->lte(now()) ?? false;
    }

    public function getHasStartedAttribute(): bool
    {
        return $this->scheduled_start_at?->lte(now()) ?? false;
    }

    public function getDisplayStatusAttribute(): string
    {
        if (in_array($this->status, ['completed', 'cancelled'], true)) {
            return $this->status;
        }

        if ($this->has_ended) {
            return 'ended';
        }

        if ($this->status === 'published' && $this->has_started) {
            return 'in_progress';
        }

        return $this->status;
    }

    public function scopeWithDisplayStatus(Builder $query, string $status, mixed $at = null): Builder
    {
        $at ??= now();

        return match ($status) {
            'draft' => $query->where('status', 'draft')->where('scheduled_end_at', '>', $at),
            'published' => $query->where('status', 'published')->where('scheduled_start_at', '>', $at),
            'in_progress' => $query->where('scheduled_end_at', '>', $at)
                ->where(fn (Builder $query) => $query->where('status', 'in_progress')
                    ->orWhere(fn (Builder $query) => $query->where('status', 'published')->where('scheduled_start_at', '<=', $at))),
            'ended' => $query->where('scheduled_end_at', '<=', $at)->whereNotIn('status', ['completed', 'cancelled']),
            default => $query->where('status', $status),
        };
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
