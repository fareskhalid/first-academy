<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['parameters' => 'array', 'delivered_at' => 'datetime'];
    }

    public $incrementing = false;

    protected $keyType = 'string';
}
