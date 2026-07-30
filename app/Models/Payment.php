<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'plan_id',
        'coupon_id',
        'coupon_code',
        'amount',
        'original_amount',
        'discount_amount',
        'currency_code',
        'payment_method',
        'payment_reference',
        'phone_number',
        'pop_file_path',
        'status',
        'admin_notes',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function redemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class);
    }

    public function wasDiscounted(): bool
    {
        return (float) $this->discount_amount > 0;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}
