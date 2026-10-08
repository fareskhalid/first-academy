<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionScheduleRevision extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array { return ['before_values' => 'array', 'after_values' => 'array', 'created_at' => 'datetime']; }
    public function session() { return $this->belongsTo(ClassSession::class, 'class_session_id'); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
