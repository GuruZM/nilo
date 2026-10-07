<?php

namespace App\Models;

use App\Enums\SubscriptionReminder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'reminder_stage',
        'reminder_sent_at',
        'paused_at',
        'payment_method',
        'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminder_stage' => SubscriptionReminder::class,
            'reminder_sent_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Access runs past the due date by the grace period, so a customer who
     * pays a few hours late is never locked out mid-invoice.
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->ends_at === null || $this->graceEndsAt()->isFuture());
    }

    public function isPendingPayment(): bool
    {
        return $this->status === 'pending_payment';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * The moment an unpaid period stops granting access, or null for a plan
     * that never falls due.
     */
    public function graceEndsAt(): ?CarbonInterface
    {
        return $this->ends_at?->copy()->addHours((int) config('nilo.billing.grace_hours'));
    }
}
