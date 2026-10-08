<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseEntitlement extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['valid_from' => 'datetime', 'valid_until' => 'datetime', 'revoked_at' => 'datetime']; }
    public function enrollment() { return $this->belongsTo(Enrollment::class); }
}
