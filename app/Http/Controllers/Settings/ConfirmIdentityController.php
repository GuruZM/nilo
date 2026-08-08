<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Proves the person at the keyboard is still the account holder before a
 * sensitive change.
 *
 * Both paths write the same `auth.password_confirmed_at` session key that
 * Laravel's `password.confirm` middleware reads, so social re-authentication
 * is a second way to satisfy the existing gate rather than a parallel one.
 */
class ConfirmIdentityController extends Controller
{
    /**
     * Providers that can force a genuine re-authentication. LinkedIn is absent
     * deliberately: it has no equivalent of `prompt=login`, so it would hand
     * back a silent success that proves nothing.
     *
     * @var array<string, array<string, string>>
     */
    private const REAUTH_PARAMETERS = [
        'google' => ['prompt' => 'login'],
    ];

    /**
     * Actions a security page may ask us to resume once identity is confirmed.
     *
     * @var list<string>
     */
    private const RESUMABLE_ACTIONS = ['enable', 'disable', 'recovery-codes', 'register-passkey', 'delete-passkey'];

    /**
     * Pages that may send a user through provider re-authentication, keyed by
     * the token they pass in the `return` query parameter. Confirmation is
     * shared across security settings, so the return leg has to know which
     * page started the round trip.
     *
     * @var array<string, string>
     */
    private const RETURN_ROUTES = [
        'two-factor' => 'two-factor.show',
        'passkeys' => 'passkeys.show',
    ];

    /**
     * @return list<string>
     */
    public static function supportedProviders(): array
    {
        return array_keys(self::REAUTH_PARAMETERS);
    }

    /**
     * Whether a re-authentication round trip through this provider is in
     * flight.
     *
     * Providers return to the single redirect URI registered for sign-in, so
     * that controller has to ask who the return leg belongs to. The intent and
     * origin are both nullable, hence a marker of their own rather than
     * inferring one from the other keys.
     */
    public static function isPending(Request $request, string $provider): bool
    {
        return $request->session()->get('confirm_identity.provider') === $provider;
    }

    /**
     * Whether the session has been confirmed recently enough to satisfy the
     * `password.confirm` middleware. Mirrors Laravel's own window check so the
     * UI and the gate never disagree.
     */
    public static function isConfirmed(Request $request): bool
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at', 0);

        return (time() - $confirmedAt) < config('auth.password_timeout');
    }

    /**
     * Confirm using the account password.
     */
    public function password(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();

        if (! $user->hasPassword() || ! Hash::check($request->string('password')->value(), $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $this->markConfirmed($request);

        return back();
    }

    /**
     * Send the user back to their provider to re-authenticate.
     */
    public function redirectToProvider(Request $request, string $provider): RedirectResponse
    {
        abort_unless(filled($request->user()->providerId($provider)), 403);

        $intent = $request->query('intent');
        $returnTo = $request->query('return');

        $request->session()->put(
            'confirm_identity.intent',
            in_array($intent, self::RESUMABLE_ACTIONS, true) ? $intent : null,
        );

        $request->session()->put(
            'confirm_identity.return_to',
            array_key_exists($returnTo, self::RETURN_ROUTES) ? $returnTo : null,
        );

        $request->session()->put('confirm_identity.provider', $provider);

        return Socialite::driver($provider)
            ->with(self::REAUTH_PARAMETERS[$provider])
            ->redirect();
    }

    /**
     * Handle the return leg from the provider.
     *
     * Reached by delegation from the provider's sign-in callback rather than by
     * a route of its own — see {@see isPending()}.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $intent = $request->session()->pull('confirm_identity.intent');
        $returnTo = $request->session()->pull('confirm_identity.return_to');
        $request->session()->forget('confirm_identity.provider');

        try {
            $providerUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return $this->backToOrigin($returnTo)
                ->with('error', 'We could not verify you with that provider. Please try again.');
        }

        if ((string) $providerUser->getId() !== (string) $request->user()->providerId($provider)) {
            return $this->backToOrigin($returnTo)
                ->with('error', 'That account is not the one linked to this profile. Please try again.');
        }

        $this->markConfirmed($request);

        $response = $this->backToOrigin($returnTo);

        return $intent ? $response->with('resumeAction', $intent) : $response;
    }

    private function markConfirmed(Request $request): void
    {
        $request->session()->put('auth.password_confirmed_at', time());
    }

    private function backToOrigin(?string $returnTo): RedirectResponse
    {
        return redirect()->route(self::RETURN_ROUTES[$returnTo] ?? 'two-factor.show');
    }
}
