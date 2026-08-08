<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * The stateless stand-in for the web's `login.id` session key.
 *
 * A browser can be told to hold half an authentication in its session; a phone
 * cannot. So the half-authenticated state is sealed into a token the client
 * carries between the password step and the code step. Encryption is what
 * makes it safe to hand over: the payload is unreadable and unforgeable
 * without the app key, and it carries its own expiry so a stolen challenge is
 * worthless within minutes.
 */
class TwoFactorChallenge
{
    private const LIFETIME_MINUTES = 5;

    public static function issue(User $user): string
    {
        return Crypt::encryptString(json_encode([
            'id' => $user->getKey(),
            'exp' => now()->addMinutes(self::LIFETIME_MINUTES)->timestamp,
        ]));
    }

    /**
     * The user this challenge was issued for.
     *
     * Every failure — tampered, expired, or naming an account that has since
     * been deleted — is reported identically so the endpoint cannot be used to
     * probe which accounts exist.
     *
     * @throws ValidationException
     */
    public static function resolve(string $challenge): User
    {
        try {
            $payload = json_decode(Crypt::decryptString($challenge), true);
        } catch (DecryptException) {
            $payload = null;
        }

        $user = is_array($payload) && ($payload['exp'] ?? 0) >= now()->timestamp
            ? User::find($payload['id'] ?? null)
            : null;

        if (! $user) {
            throw ValidationException::withMessages([
                'challenge' => __('This sign-in has expired. Please enter your password again.'),
            ]);
        }

        return $user;
    }
}
