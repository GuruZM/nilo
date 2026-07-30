<?php

namespace App\Listeners;

use App\Notifications\WelcomeEmail;
use Illuminate\Auth\Events\Verified;

class SendWelcomeEmailAfterVerification
{
    /**
     * Send the branded welcome email once a user verifies their email.
     * OAuth sign-ups arrive already verified and receive their welcome
     * email from the registration flow instead.
     */
    public function handle(Verified $event): void
    {
        $event->user->notify(new WelcomeEmail);
    }
}
