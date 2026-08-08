<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Concerns\IssuesApiTokens;
use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleAccountLinker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Native Google sign-in.
 *
 * The browser flow cannot be reused on a phone: it depends on a redirect and a
 * session. Instead the app's own Google SDK signs the user in and hands over an
 * ID token, which is verified here and swapped for a bearer token. No new
 * redirect URI has to be registered with Google for this — the token is
 * addressed to the platform's client id, which is what the audience check
 * below confirms.
 */
class GoogleTokenController extends Controller
{
    use IssuesApiTokens;

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function store(Request $request, GoogleAccountLinker $accounts): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
            'intent' => ['nullable', 'in:login,register'],
            'terms' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $claims = $this->verifiedClaims($request->string('id_token')->value());

        $user = $accounts->find($claims['sub'], $claims['email'] ?? null);

        if (! $user) {
            if ($request->input('intent') !== 'register') {
                throw ValidationException::withMessages([
                    'id_token' => 'No account found for that Google account. Please sign up first.',
                ]);
            }

            if (! $request->boolean('terms')) {
                throw ValidationException::withMessages([
                    'terms' => 'You must agree to the Terms and Conditions to create an account.',
                ]);
            }

            $user = $accounts->create($claims['sub'], $claims['email'], $claims['name'] ?? null);
        }

        return $this->issueToken($user, $request->string('device_name')->value());
    }

    /**
     * Verify the ID token with Google and return its claims.
     *
     * Google's tokeninfo endpoint checks the signature, so what is left to
     * check here is that the token was minted for *this* app and is still
     * valid. Every one of these is load bearing: without the audience check any
     * Google app's token would be accepted, and without `email_verified` an
     * unverified Google address could be used to claim an existing account by
     * email in {@see GoogleAccountLinker::find()}.
     *
     * @return array<string, mixed>
     */
    private function verifiedClaims(string $idToken): array
    {
        $response = rescue(
            fn () => Http::timeout(10)->get(config('services.google.tokeninfo_url'), ['id_token' => $idToken]),
            report: false,
        );

        $claims = $response?->successful() ? (array) $response->json() : [];

        $audiences = (array) config('services.google.client_ids');

        $valid = filled($claims['sub'] ?? null)
            && filled($claims['email'] ?? null)
            && $audiences !== []
            && in_array($claims['aud'] ?? null, $audiences, true)
            && in_array($claims['iss'] ?? null, self::ISSUERS, true)
            && filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && (int) ($claims['exp'] ?? 0) > now()->timestamp;

        if (! $valid) {
            throw ValidationException::withMessages([
                'id_token' => 'Unable to sign in with Google. Please try again.',
            ]);
        }

        return $claims;
    }
}
