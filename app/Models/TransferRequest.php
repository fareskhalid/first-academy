<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
