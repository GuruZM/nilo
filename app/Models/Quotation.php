<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    /**
     * The company this quotation belongs to.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The client this quotation is for.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The items for this quotation.
     */
    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }
}
