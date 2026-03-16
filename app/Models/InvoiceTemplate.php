<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'type',
        'name',
        'is_default',
        'settings',
        'terms_html',
        'footer_html',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'settings' => 'array',
    ];
}
