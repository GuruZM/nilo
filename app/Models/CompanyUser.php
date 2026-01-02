<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyUser extends Model
{
    /**
     * The company for this record.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The user for this record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
