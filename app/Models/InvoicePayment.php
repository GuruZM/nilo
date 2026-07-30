<?php

namespace App\Models;

use App\Models\Concerns\FreezesExchangeRate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment received against one invoice.
 *
 * The receipt a client is given is this row printed through the shared sheet,
 * so there is no separate receipt entity to keep in step.
 */
class InvoicePayment extends Model
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
        'recorded_by',
        'receipt_number',
        'amount',
        'currency_code',
        'paid_on',
        'method',
        'reference',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'exchange_rate_to_base' => 'float',
            'exchange_rate_fetched_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
}
