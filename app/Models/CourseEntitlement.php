<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseEntitlement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['valid_from' => 'datetime', 'valid_until' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function paymentProof()
    {
        return $this->belongsTo(PaymentProof::class, 'source_id')->where('source_type', 'payment_proof');
    }
}
