<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company Nilo buys from.
 *
 * Structurally a mirror of {@see Client}, kept as its own table so a vendor can
 * never surface in a customer list or a revenue roll-up. The column names match
 * `clients` on purpose: the shared document sheet reads the same four fields off
 * whichever counterparty it is handed.
 */
class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'tpin',
        'address',
        'city',
        'country',
        'contact_person',
        'notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
