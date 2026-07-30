<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency_code',
        'billing_period',
        'max_companies',
        'max_invoices',
        'max_quotations',
        'max_purchase_orders',
        'max_invoice_templates',
        'max_quotation_templates',
        'can_upload_custom_template',
        'is_active',
        'is_public',
        'is_popular',
        'sort_order',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'can_upload_custom_template' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_popular' => 'boolean',
            'features' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Coupons explicitly restricted to this plan. Unrestricted coupons apply
     * here too without appearing in this relation.
     */
    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class);
    }

    /**
     * When a billing period that starts now runs out. The single source of
     * truth for subscription end dates, so a yearly plan is never billed as
     * though it were monthly.
     */
    public function periodEndFrom(CarbonInterface $start): CarbonInterface
    {
        return $this->billing_period === 'yearly'
            ? $start->copy()->addYear()
            : $start->copy()->addMonth();
    }

    /**
     * Plans a visitor may see and buy. Complimentary plans stay active — admins
     * assign them and their limits are enforced — but are never listed or sold.
     *
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order');
    }

    public function isFreeTier(): bool
    {
        return $this->slug === 'free';
    }

    public function isEnterprise(): bool
    {
        return $this->slug === 'enterprise';
    }

    public function hasUnlimitedInvoices(): bool
    {
        return $this->max_invoices === -1;
    }

    public function hasUnlimitedQuotations(): bool
    {
        return $this->max_quotations === -1;
    }

    public function hasUnlimitedPurchaseOrders(): bool
    {
        return $this->max_purchase_orders === -1;
    }

    public function hasUnlimitedCompanies(): bool
    {
        return $this->max_companies === -1;
    }
}
