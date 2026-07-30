<?php

namespace App\Listeners;

use App\Notifications\WelcomeEmail;
use Illuminate\Auth\Events\Registered;

class SendWelcomeEmail
{
    /**
     * Send the welcome email to users who register already verified,
     * such as those signing up through an OAuth provider. Unverified
     * sign-ups instead receive the combined welcome and verification email.
     */
    public function handle(Registered $event): void
    {
        if ($event->user->hasVerifiedEmail()) {
            $event->user->notify(new WelcomeEmail);
        }
    }
}
