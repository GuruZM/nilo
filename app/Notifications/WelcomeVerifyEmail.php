<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Build the branded welcome and verification email.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Nilo, verify your email')
            ->view('emails.welcome-verify', [
                'name' => $notifiable->name,
                'url' => $this->verificationUrl($notifiable),
            ]);
    }
}
