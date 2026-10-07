<?php

namespace App\Models;

use App\Models\Concerns\FreezesExchangeRate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quotation extends Model
{
    /** @use HasFactory<\Database\Factories\QuotationFactory> */
    use FreezesExchangeRate, HasFactory;

    protected $fillable = [
        'company_id',
        'client_id',
        'quotation_template_id',
        'created_by',
        'number',
        'reference',
        'title',
        'issue_date',
        'valid_until',
        'currency_code',
        'subtotal',
        'discount_total',
        'quotation_discount',
        'tax_total',
        'tax_percent',
        'total',
        'status',
        'notes',
        'terms',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'quotation_discount' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'total' => 'decimal:2',
            'exchange_rate_to_base' => 'float',
            'exchange_rate_fetched_at' => 'datetime',
        ];
    }

    /**
     * The company this quotation belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The client this quotation is for.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The template controlling how this quotation is presented.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class, 'quotation_template_id');
    }

    /**
     * The items for this quotation.
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    /**
     * The invoice raised from this quotation, if it has been invoiced.
     *
     * At most one exists: `invoices.quotation_id` carries a unique index.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
