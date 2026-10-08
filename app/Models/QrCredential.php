<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrCredential extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['token_hash', 'short_code_hash'];
    protected function casts(): array { return ['issued_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime']; }
    public function session() { return $this->belongsTo(ClassSession::class, 'class_session_id'); }
}
