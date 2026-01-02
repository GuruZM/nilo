<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    /**
     * The company this subscription belongs to.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
