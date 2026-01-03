<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
     
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
        'tax_total',
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
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'has_delivery_note' => 'boolean',
        'is_recurring' => 'boolean',
        'recurrence_start_date' => 'date',
        'recurrence_end_date' => 'date',
        'next_run_at' => 'datetime',
    ];

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
    return $this->belongsTo(InvoiceTemplate::class, 'template_id');
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
