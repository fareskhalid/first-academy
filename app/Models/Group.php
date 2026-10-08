<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'capacity' => 'integer'];
    }

    public function offering()
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function classSessions() { return $this->hasMany(ClassSession::class); }
}
