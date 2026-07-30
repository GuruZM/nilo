<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rate a company entered by hand, overriding the synced one.
 *
 * The override is deliberately short-lived: it applies only until the next
 * successful sync of the same pair. See {@see \App\Services\EffectiveRates}
 * for the precedence rule — this model does not decide it.
 */
class CompanyExchangeRate extends Model
{
    protected $fillable = [
        'company_id',
        'base_code',
        'quote_code',
        'rate',
        'set_at',
        'set_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'set_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
