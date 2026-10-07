<?php

namespace App\Services;

use App\Enums\SubscriptionReminder;
use App\Models\Subscription;
use App\Notifications\SubscriptionPaused;
use App\Notifications\SubscriptionPaymentDue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Chases payment for subscriptions that are falling due ("dunning").
 *
 * Nothing renews itself, so without this a subscriber only found out their
 * plan had lapsed when the app turned them away. Reminders go out three days
 * and one day before the due date and once more inside the grace period;
 * a period still unpaid when the grace period ends is paused.
 */
class SubscriptionDunning
{
    /**
     * A pause is only worth announcing while the lapse is fresh. The first
     * sweep after this shipped found plans that ran out months ago, and
     * telling those customers they had just been paused would be news to
     * nobody and wrong besides.
     */
    private const ANNOUNCE_PAUSES_WITHIN_DAYS = 7;

    public function run(): SubscriptionDunningResult
    {
        $errors = 0;

        $paused = $this->pauseLapsed($errors);
        $reminded = $this->sendReminders($errors);

        return SubscriptionDunningResult::make($reminded, $paused, $errors);
    }

    private function pauseLapsed(int &$errors): int
    {
        $paused = 0;

        $this->chasable()
            ->where('ends_at', '<=', now()->subHours($this->graceHours()))
            ->with(['user', 'plan'])
            ->chunkById(100, function ($subscriptions) use (&$paused, &$errors): void {
                foreach ($subscriptions as $subscription) {
                    try {
                        $this->pause($subscription);
                        $paused++;
                    } catch (Throwable $e) {
                        $errors++;

                        Log::error('Pausing a lapsed subscription failed', [
                            'subscription_id' => $subscription->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $paused;
    }

    private function pause(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        if ($this->isBillable($subscription)
            && $subscription->ends_at->gte(now()->subDays(self::ANNOUNCE_PAUSES_WITHIN_DAYS))) {
            $subscription->user->notify(new SubscriptionPaused($subscription));
        }
    }

    private function sendReminders(int &$errors): int
    {
        $reminded = 0;

        $this->chasable()
            ->whereBetween('ends_at', [now()->subHours($this->graceHours()), now()->addDays(3)])
            ->whereHas('plan', fn (Builder $query) => $query->where('price', '>', 0))
            ->with(['user', 'plan'])
            ->chunkById(100, function ($subscriptions) use (&$reminded, &$errors): void {
                foreach ($subscriptions as $subscription) {
                    try {
                        if ($this->remind($subscription)) {
                            $reminded++;
                        }
                    } catch (Throwable $e) {
                        $errors++;

                        Log::error('Sending a payment reminder failed', [
                            'subscription_id' => $subscription->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $reminded;
    }

    private function remind(Subscription $subscription): bool
    {
        $reminder = SubscriptionReminder::dueFor($subscription->ends_at, now());

        if ($reminder === null || ! $reminder->isAfter($subscription->reminder_stage)) {
            return false;
        }

        $subscription->update([
            'reminder_stage' => $reminder,
            'reminder_sent_at' => now(),
        ]);

        $subscription->user->notify(new SubscriptionPaymentDue($subscription, $reminder));

        return true;
    }

    /**
     * Running subscriptions that fall due, less any whose owner has already
     * paid and is waiting on an admin or the gateway to confirm it. Chasing
     * those would ask a customer to pay twice.
     */
    private function chasable(): Builder
    {
        return Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereDoesntHave('user.payments', fn (Builder $query) => $query->where('status', 'pending'));
    }

    private function isBillable(Subscription $subscription): bool
    {
        return $subscription->plan !== null && (float) $subscription->plan->price > 0;
    }

    private function graceHours(): int
    {
        return (int) config('nilo.billing.grace_hours');
    }
}
