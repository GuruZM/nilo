<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    /** @use HasFactory<\Database\Factories\CouponFactory> */
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'currency_code',
        'starts_at',
        'expires_at',
        'max_redemptions',
        'once_per_user',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'max_redemptions' => 'integer',
            'redemptions_count' => 'integer',
            'once_per_user' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Codes are case-insensitive to the customer but stored one way, so a
     * lookup and a save never disagree about "SAVE20" versus "save20".
     */
    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * Plans this coupon is restricted to. Empty means every purchasable plan.
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * @param  Builder<Coupon>  $query
     * @return Builder<Coupon>
     */
    public function scopeCode(Builder $query, string $code): Builder
    {
        return $query->where('code', self::normalizeCode($code));
    }

    public function isPercentage(): bool
    {
        return $this->discount_type === self::TYPE_PERCENTAGE;
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || ! $this->starts_at->isFuture();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasRedemptionsLeft(): bool
    {
        return $this->max_redemptions === null
            || $this->redemptions_count < $this->max_redemptions;
    }

    /**
     * A coupon with no plan restrictions applies everywhere. A fixed-amount
     * coupon additionally has to be denominated in the plan's currency —
     * "K50 off" means nothing against a plan priced in dollars.
     */
    public function appliesToPlan(Plan $plan): bool
    {
        if (! $this->isPercentage() && $this->currency_code !== $plan->currency_code) {
            return false;
        }

        $restrictions = $this->relationLoaded('plans')
            ? $this->plans
            : $this->plans()->get();

        return $restrictions->isEmpty() || $restrictions->contains('id', $plan->id);
    }

    public function wasRedeemedBy(User $user): bool
    {
        return $this->redemptions()->where('user_id', $user->id)->exists();
    }

    /**
     * Money taken off the plan price, never more than the price itself — an
     * over-large fixed coupon settles the bill rather than owing change.
     */
    public function discountFor(Plan $plan): float
    {
        $price = (float) $plan->price;

        $discount = $this->isPercentage()
            ? $price * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        return round(min($discount, $price), 2);
    }

    /**
     * How the discount reads on a receipt, e.g. "20% off" or "ZMW 50.00 off".
     */
    public function label(): string
    {
        if ($this->isPercentage()) {
            return rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.').'% off';
        }

        return $this->currency_code.' '.number_format((float) $this->discount_value, 2).' off';
    }
}
