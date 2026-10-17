<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReview extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function proof()
    {
        return $this->belongsTo(PaymentProof::class, 'payment_proof_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
