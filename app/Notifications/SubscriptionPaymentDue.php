<?php

namespace App\Notifications;

use App\Enums\SubscriptionReminder;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentDue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public SubscriptionReminder $reminder,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the reminder from the real due date rather than the stage's name:
     * a period that starts inside the window gets the three-day reminder with
     * fewer than three days left.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan;
        $dueOn = $this->subscription->ends_at->format('j F Y');
        $price = $plan->currency_code.' '.number_format((float) $plan->price, 2)
            .($plan->billing_period === 'yearly' ? ' per year' : ' per month');

        if ($this->reminder === SubscriptionReminder::Overdue) {
            return (new MailMessage)
                ->subject('Your Nilo payment is overdue')
                ->view('emails.subscription-billing', [
                    'heading' => 'Your payment is overdue',
                    'paragraphs' => [
                        "Hi {$notifiable->name}, payment for your {$plan->name} plan ({$price}) was due on {$dueOn} and we have not received it yet.",
                        'You still have access for now, but if payment has not arrived within '
                            .config('nilo.billing.grace_hours')
                            .' hours of the due date your subscription will be paused.',
                    ],
                    'actionLabel' => 'Pay now',
                    'actionUrl' => route('subscription.select'),
                ]);
        }

        $subject = $this->reminder === SubscriptionReminder::DueTomorrow
            ? "Final reminder: your Nilo payment is due on {$dueOn}"
            : "Your Nilo payment is due on {$dueOn}";

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.subscription-billing', [
                'heading' => 'Your plan is due for renewal',
                'paragraphs' => [
                    "Hi {$notifiable->name}, your {$plan->name} plan ({$price}) is due on {$dueOn}.",
                    'Nilo does not renew automatically, so please pay before then to keep creating invoices, quotations and receipts without interruption.',
                ],
                'actionLabel' => 'Renew now',
                'actionUrl' => route('subscription.select'),
            ]);
    }
}
