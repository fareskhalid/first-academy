<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function offering()
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function classSessions()
    {
        return $this->hasMany(ClassSession::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
