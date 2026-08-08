<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    /**
     * The payment_method value that marks a row as settled by DPO. The other
     * values — mobile_money, bank_transfer, coupon — are all hand-settled.
     * Payments taken before the manual routes were reworked carry the retired
     * airtel_money value and are still hand-settled the same way.
     */
    public const METHOD_DPO = 'dpo';

    protected $fillable = [
        'user_id',
        'subscription_id',
        'plan_id',
        'coupon_id',
        'coupon_code',
        'amount',
        'original_amount',
        'discount_amount',
        'charged_amount',
        'charged_currency_code',
        'charged_exchange_rate',
        'charged_rate_fetched_at',
        'currency_code',
        'payment_method',
        'payment_reference',
        'company_ref',
        'dpo_transaction_token',
        'phone_number',
        'pop_file_path',
        'status',
        'gateway_status',
        'gateway_response',
        'admin_notes',
        'confirmed_by',
        'confirmed_at',
        'paid_at',
        'verified_at',
    ];

    /**
     * DPO's verify response carries card metadata — a masked PAN, an account
     * reference — and this model is handed straight to Inertia as a page prop
     * with no resource layer in between. The admin screen re-exposes it with
     * makeVisible(); nothing the subscriber sees needs it.
     *
     * @var list<string>
     */
    protected $hidden = [
        'gateway_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'charged_amount' => 'decimal:2',
            'charged_exchange_rate' => 'float',
            'charged_rate_fetched_at' => 'datetime',
            'gateway_response' => 'array',
            'confirmed_at' => 'datetime',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
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

    /**
     * Whether a gateway settled this payment rather than an admin eyeballing
     * a transfer. Gateway payments must never be hand-confirmed.
     */
    public function isGateway(): bool
    {
        return $this->payment_method === self::METHOD_DPO;
    }

    /**
     * Gateway checkouts this user could still walk back into and pay.
     *
     * DPO holds a transaction for PTL hours whether or not the customer ever
     * returns to it, so every extra checkout leaves another live, payable
     * transaction behind. Anything past its PTL is left out: DPO has expired it
     * and sending the customer back would land them on a dead page.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeResumableGatewayCheckouts(Builder $query, User $user): Builder
    {
        return $query
            ->where('user_id', $user->id)
            ->where('payment_method', self::METHOD_DPO)
            ->where('status', 'pending')
            ->whereNotNull('dpo_transaction_token')
            ->where('created_at', '>=', now()->subHours((int) config('services.dpo.ptl_hours', 24)));
    }

    /**
     * Whether this transaction is for exactly what is being asked for again.
     *
     * Anything less than an exact match has to become a new transaction: a
     * customer who switched plan or currency must not be handed back a token
     * that charges them for what they changed their mind about.
     *
     * @param  array{amount: float|string, currency: string}  $charge
     */
    public function matchesCheckout(Plan $plan, ?Coupon $coupon, array $charge): bool
    {
        return $this->plan_id === $plan->id
            && $this->coupon_id === $coupon?->id
            && $this->chargedCurrency() === $charge['currency']
            && abs((float) $this->charged_amount - (float) $charge['amount']) < 0.01;
    }

    /**
     * The currency DPO was actually asked to take, which is the ledger
     * currency unless the subscriber chose to pay in another one.
     */
    public function chargedCurrency(): string
    {
        return $this->charged_currency_code ?? $this->currency_code;
    }

    /**
     * The figure DPO was actually asked to take, in {@see chargedCurrency()}.
     */
    public function chargedAmount(): string
    {
        return (string) ($this->charged_amount ?? $this->amount);
    }
}
