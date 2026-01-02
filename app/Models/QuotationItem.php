<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    /**
     * The quotation this item belongs to.
     */
    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
