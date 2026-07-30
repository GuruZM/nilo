<?php

namespace App\Models;

use App\Contracts\RenderableDocument;
use App\Enums\DocumentType;
use App\Models\Concerns\FreezesExchangeRate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order placed with a supplier.
 *
 * The only document Nilo issues that points away from the customer, which is
 * why it is the only one addressed to a {@see Supplier}.
 */
class PurchaseOrder extends Model implements RenderableDocument
{
    /** @use HasFactory<\Database\Factories\PurchaseOrderFactory> */
    use FreezesExchangeRate, HasFactory;

    /**
     * @var list<string>
     */
    public const STATUSES = ['draft', 'sent', 'approved', 'received', 'cancelled'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_order_template_id',
        'created_by',
        'number',
        'reference',
        'title',
        'issue_date',
        'expected_date',
        'currency_code',
        'delivery_address',
        'subtotal',
        'discount_total',
        'purchase_order_discount',
        'tax_total',
        'tax_percent',
        'total',
        'status',
        'notes',
        'terms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'purchase_order_discount' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'total' => 'decimal:2',
            'exchange_rate_to_base' => 'float',
            'exchange_rate_fetched_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function documentType(): DocumentType
    {
        return DocumentType::PurchaseOrder;
    }

    public function counterparty(): ?Model
    {
        return $this->supplier;
    }

    /**
     * @return Collection<int, Model>
     */
    public function printableItems(): Collection
    {
        return $this->items()->get();
    }

    public function chosenTemplateId(): ?int
    {
        return $this->purchase_order_template_id;
    }
}
