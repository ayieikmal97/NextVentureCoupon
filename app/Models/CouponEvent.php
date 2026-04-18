<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponEvent extends Model
{
    protected $guarded = [];

    // Cast the JSON columns back to PHP arrays automatically
    protected $casts = [
        'rule_snapshot' => 'array',
        'metadata' => 'array',
    ];
}
