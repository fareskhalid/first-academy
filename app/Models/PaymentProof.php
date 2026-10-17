<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProof extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'reviewed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function supersedes()
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revoker()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function entitlement()
    {
        return $this->hasOne(CourseEntitlement::class, 'source_id')->where('source_type', 'payment_proof');
    }

    public function reviews()
    {
        return $this->hasMany(PaymentReview::class)->latest('id');
    }
}
