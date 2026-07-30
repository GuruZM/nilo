<?php

namespace App\Models;

use App\Contracts\RenderableDocument;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Goods dispatched against an invoice.
 *
 * There is deliberately no money on this model. The shared sheet suppresses
 * every price column for this type, so a delivery note cannot leak what the
 * goods cost even if a caller passes totals in.
 */
class DeliveryNote extends Model implements RenderableDocument
{
    /** @use HasFactory<\Database\Factories\DeliveryNoteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    public const STATUSES = ['draft', 'dispatched', 'delivered'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'client_id',
        'invoice_id',
        'delivery_note_template_id',
        'created_by',
        'number',
        'reference',
        'issue_date',
        'delivery_date',
        'currency_code',
        'deliver_to',
        'delivery_address',
        'received_by',
        'received_on',
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
            'delivery_date' => 'date',
            'received_on' => 'date',
        ];
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

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class)->orderBy('sort_order');
    }

    public function documentType(): DocumentType
    {
        return DocumentType::DeliveryNote;
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
        return $this->delivery_note_template_id;
    }
}
