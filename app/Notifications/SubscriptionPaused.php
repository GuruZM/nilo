<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaused extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

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
     * Build the email telling a subscriber their unpaid plan has been paused.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan;
        $dueOn = $this->subscription->ends_at->format('j F Y');

        return (new MailMessage)
            ->subject('Your Nilo subscription is paused')
            ->view('emails.subscription-billing', [
                'heading' => 'Your subscription is paused',
                'paragraphs' => [
                    "Hi {$notifiable->name}, we did not receive payment for your {$plan->name} plan, which was due on {$dueOn}, so your subscription has been paused.",
                    'Your companies, clients and documents are all still here. Renew your plan and you will pick up exactly where you left off.',
                ],
                'actionLabel' => 'Renew my plan',
                'actionUrl' => route('subscription.select'),
            ]);
    }
}
