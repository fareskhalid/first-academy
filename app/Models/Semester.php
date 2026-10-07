<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'archived_at' => 'datetime'];
    }

    public function offerings()
    {
        return $this->hasMany(CourseOffering::class);
    }
}
