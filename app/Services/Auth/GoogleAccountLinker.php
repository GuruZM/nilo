<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Resolves a Google identity to a local account.
 *
 * Shared by the web OAuth callback and the mobile ID-token exchange so the two
 * front doors cannot disagree about who an account belongs to. Matching on the
 * email as well as the provider id is deliberate: someone who signed up with a
 * password and later taps "Continue with Google" should land back in their own
 * account rather than a duplicate.
 */
class GoogleAccountLinker
{
    /**
     * Find the account for this Google identity, linking the provider id to an
     * existing account where one matches.
     */
    public function find(string $googleId, ?string $email): ?User
    {
        $user = User::query()
            ->where('google_id', $googleId)
            ->when($email, fn ($query) => $query->orWhere('email', $email))
            ->first();

        if ($user && ! $user->google_id) {
            $user->update(['google_id' => $googleId]);
        }

        return $user;
    }

    /**
     * Create an account for a Google identity that has none yet.
     *
     * The email is taken as verified because Google has already verified it —
     * callers must confirm that claim before getting here.
     */
    public function create(string $googleId, string $email, ?string $name = null): User
    {
        $user = User::create([
            'name' => $name ?: $email,
            'email' => $email,
            'google_id' => $googleId,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Registered($user));

        $user->subscribeToFreePlan();

        return $user;
    }
}
