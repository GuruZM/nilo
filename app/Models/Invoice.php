<?php

namespace App\Models;

use App\Models\Concerns\FreezesExchangeRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use FreezesExchangeRate, HasFactory;

    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_VOID = 'void';

    /**
     * Statuses that represent money the company is still owed.
     *
     * An invoice leaves `pending` the moment it is emailed to the client, so
     * outstanding money cannot be identified by a single status — doing that
     * drops the amount out of every roll-up as soon as the invoice is sent.
     * A partly settled invoice is still owed for whatever remains.
     *
     * @var list<string>
     */
    public const OUTSTANDING_STATUSES = ['pending', 'sent', 'partially_paid', 'overdue'];

    protected $fillable = [
        'company_id',
        'client_id',
        'invoice_template_id',
        'created_by',
        'number',
        'reference',
        'title',
        'issue_date',
        'due_date',
        'currency_code',
        'subtotal',
        'discount_total',
        'invoice_discount',
        'tax_total',
        'tax_percent',
        'total',
        'status',
        'has_delivery_note',
        'is_recurring',
        'recurrence_frequency',
        'recurrence_interval',
        'recurrence_start_date',
        'recurrence_end_date',
        'next_run_at',
        'notes',
        'terms',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'invoice_discount' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'total' => 'decimal:2',
        'has_delivery_note' => 'boolean',
        'is_recurring' => 'boolean',
        'recurrence_start_date' => 'date',
        'recurrence_end_date' => 'date',
        'next_run_at' => 'datetime',
        'exchange_rate_to_base' => 'float',
        'exchange_rate_fetched_at' => 'datetime',
    ];

    /**
     * Invoices whose money has been collected.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Invoices still owed — issued or emailed, but neither paid nor voided.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', self::OUTSTANDING_STATUSES);
    }

    /**
     * Whether this invoice still represents money owed.
     */
    public function isOutstanding(): bool
    {
        return in_array($this->status, self::OUTSTANDING_STATUSES, true);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function template()
    {
        return $this->belongsTo(InvoiceTemplate::class, 'invoice_template_id');
    }

    /**
     * The client this invoice is for.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The items for this invoice.
     */
}
