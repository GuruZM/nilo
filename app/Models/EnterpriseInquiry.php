<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnterpriseInquiry extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_name',
        'message',
        'is_handled',
    ];

    protected function casts(): array
    {
        return [
            'is_handled' => 'boolean',
        ];
    }
}
