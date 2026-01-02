<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
         protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'tpin',
        'address',
        'city',
        'country',
        'notes',
            'contact_person',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The invoices for this client.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The quotations for this client.
     */
    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }
}
