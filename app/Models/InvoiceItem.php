<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
      protected $fillable = [
        'invoice_id',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
