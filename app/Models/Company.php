<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'email',
        'phone',
        'tpin',
        'address',
        'logo_path',
        'primary_color',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'logo_url',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        $logoPath = $this->logo_path;

        if (! is_string($logoPath) || $logoPath === '') {
            return null;
        }

        if (Str::startsWith($logoPath, ['http://', 'https://', '//'])) {
            return $logoPath;
        }

        $normalizedPath = ltrim($logoPath, '/');

        if (Str::startsWith($normalizedPath, 'storage/')) {
            $normalizedPath = Str::after($normalizedPath, 'storage/');
        }

        if (Str::startsWith($normalizedPath, 'public/')) {
            $normalizedPath = Str::after($normalizedPath, 'public/');
        }

        if ($normalizedPath === '') {
            return null;
        }

        return '/storage/'.$normalizedPath;
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'company_user')->withPivot('role')->withTimestamps();
    }

    /**
     * The owner of the company.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The clients of the company.
     */
    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    /**
     * The invoices of the company.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The quotations of the company.
     */
    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * The subscriptions of the company.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * CompanyUser pivot records for this company.
     */
    public function companyUsers()
    {
        return $this->hasMany(CompanyUser::class);
    }
}
