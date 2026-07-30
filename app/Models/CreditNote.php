<?php

namespace App\Models;

use App\Contracts\RenderableDocument;
use App\Enums\DocumentType;
use App\Models\Concerns\FreezesExchangeRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A credit raised against an invoice.
 *
 * Correcting an invoice by voiding it destroys the record of what was billed.
 * A credit note leaves the original standing and books the correction against
 * it, which is both what auditors expect and what keeps the balance honest.
 */
class CreditNote extends Model implements RenderableDocument
{
    /** @use HasFactory<\Database\Factories\CreditNoteFactory> */
    use FreezesExchangeRate, HasFactory;

    /**
     * Statuses whose value actually comes off the invoice. A draft is still
     * being written and must not move anybody's balance.
     *
     * @var list<string>
     */
    public const APPLIED_STATUSES = ['issued'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'client_id',
        'invoice_id',
        'credit_note_template_id',
        'created_by',
        'number',
        'reference',
        'title',
        'reason',
        'issue_date',
        'currency_code',
        'subtotal',
        'discount_total',
        'credit_note_discount',
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
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'credit_note_discount' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'total' => 'decimal:2',
            'exchange_rate_to_base' => 'float',
            'exchange_rate_fetched_at' => 'datetime',
        ];
    }

    /**
     * Credit notes whose value has been applied to an invoice.
     */
    public function scopeApplied(Builder $query): Builder
    {
        return $query->whereIn('status', self::APPLIED_STATUSES);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class, 'credit_note_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class)->orderBy('sort_order');
    }

    public function documentType(): DocumentType
    {
        return DocumentType::CreditNote;
    }

    public function counterparty(): ?Model
    {
        return $this->client;
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
        return $this->credit_note_template_id;
    }
}
