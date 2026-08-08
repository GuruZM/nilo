<?php

namespace App\Models;

use App\Contracts\RenderableDocument;
use App\Enums\DocumentType;
use App\Models\Concerns\FreezesExchangeRate;
use App\Services\InvoiceSettlement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment received, and the receipt it prints as.
 *
 * The receipt a client is given is this row printed through the shared sheet,
 * so there is no separate receipt entity to keep in step.
 *
 * Most rows settle an invoice. Some do not: money is also received where no
 * invoice was raised, and those rows carry a `client_id` and a `description`
 * instead of an `invoice_id`. Both kinds live here so that both draw from one
 * `RCP-` sequence — see the migration that made `invoice_id` nullable.
 */
class InvoicePayment extends Model implements RenderableDocument
{
    /** @use HasFactory<\Database\Factories\InvoicePaymentFactory> */
    use FreezesExchangeRate, HasFactory;

    /**
     * @var list<string>
     */
    public const METHODS = ['cash', 'bank_transfer', 'mobile_money', 'cheque', 'card', 'other'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'invoice_id',
        'client_id',
        'recorded_by',
        'receipt_number',
        'amount',
        'balance_after',
        'currency_code',
        'paid_on',
        'method',
        'reference',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'paid_on' => 'date',
            'exchange_rate_to_base' => 'float',
            'exchange_rate_fetched_at' => 'datetime',
        ];
    }

    /**
     * Receipts raised without an invoice behind them.
     */
    public function scopeStandalone(Builder $query): Builder
    {
        return $query->whereNull('invoice_id');
    }

    /**
     * Receipts that settle an invoice.
     */
    public function scopeAgainstInvoice(Builder $query): Builder
    {
        return $query->whereNotNull('invoice_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Only set on a standalone receipt. An invoice-backed one reaches its client
     * through the invoice, so that this and the invoice cannot disagree.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Human wording for the printed receipt.
     */
    public function methodLabel(): string
    {
        return match ($this->method) {
            'bank_transfer' => 'Bank transfer',
            'mobile_money' => 'Mobile money',
            default => ucfirst((string) $this->method),
        };
    }

    public function documentType(): DocumentType
    {
        return DocumentType::Receipt;
    }

    public function counterparty(): ?Model
    {
        $this->loadMissing(['invoice.client', 'client']);

        return $this->invoice?->client ?? $this->client;
    }

    /**
     * A receipt has no lines of its own — it prints one row saying what the
     * money was for, which keeps it on the shared sheet instead of needing a
     * second layout for a single figure.
     *
     * An invoice-backed receipt names its invoice, because that is what the
     * client needs to reconcile against. A standalone one has nothing to point
     * at, so it says what it was told the payment was for.
     *
     * @return Collection<int, Model>
     */
    public function printableItems(): Collection
    {
        $this->loadMissing('invoice');

        $description = $this->invoice
            ? 'Payment for invoice '.($this->invoice->number ?? '—')
            : ($this->description ?: 'Payment received');

        return new Collection([
            new InvoiceItem([
                'description' => $description.' ('.$this->methodLabel().')',
                'quantity' => 1,
                'unit_price' => (float) $this->amount,
                'line_total' => (float) $this->amount,
            ]),
        ]);
    }

    public function chosenTemplateId(): ?int
    {
        return null;
    }

    /**
     * The sheet reads these keys off whatever it is handed. A receipt is worth
     * the amount received, not the invoice total.
     */
    public function getNumberAttribute(): ?string
    {
        return $this->receipt_number;
    }

    public function getIssueDateAttribute(): mixed
    {
        return $this->paid_on;
    }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->amount;
    }

    public function getTaxTotalAttribute(): float
    {
        return 0.0;
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->amount;
    }

    /**
     * Printed under the line so the client can see what is left to pay.
     *
     * The sheet prints notes *instead of* the type's closing line, not beneath
     * it, so the closing line is carried in here rather than left to be
     * silently dropped off every receipt that names a balance.
     */
    public function getNotesAttribute(): ?string
    {
        $this->loadMissing('invoice');

        if (! $this->invoice) {
            return null;
        }

        /**
         * The balance is frozen when the payment is recorded. A receipt is
         * proof of a transaction at a moment in time — recomputing it on
         * reprint would make the customer's copy and ours disagree. Rows
         * predating the frozen column fall back to the live figure.
         */
        $balance = $this->balance_after !== null
            ? (float) $this->balance_after
            : app(InvoiceSettlement::class)->balanceDue($this->invoice);

        $standing = $balance > 0
            ? 'Balance remaining on invoice '.$this->invoice->number.': '
                .number_format($balance, 2, '.', ',').' '.$this->currency_code
            : 'Invoice '.$this->invoice->number.' is settled in full.';

        return $this->documentType()->closingLine()."\n".$standing;
    }

    public function getTermsAttribute(): ?string
    {
        return null;
    }
}
