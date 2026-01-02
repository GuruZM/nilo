<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'precision',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'precision' => 'integer',
    ];
}
