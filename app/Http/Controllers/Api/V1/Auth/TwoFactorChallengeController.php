<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Concerns\IssuesApiTokens;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TwoFactorChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class TwoFactorChallengeController extends Controller
{
    use IssuesApiTokens;

    /**
     * Complete a sign-in that stopped at the second factor.
     *
     * Verification goes through Fortify's own provider and recovery-code
     * helpers rather than reimplementing TOTP, so the API accepts exactly the
     * codes the browser does.
     */
    public function store(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = TwoFactorChallenge::resolve($request->string('challenge')->value());

        if ($code = $request->string('code')->value()) {
            $verified = $provider->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                $code,
            );

            if (! $verified) {
                throw ValidationException::withMessages([
                    'code' => __('The provided two factor authentication code was invalid.'),
                ]);
            }

            return $this->issueToken($user, $request->string('device_name')->value());
        }

        if ($recoveryCode = $request->string('recovery_code')->value()) {
            $this->consumeRecoveryCode($user, $recoveryCode);

            return $this->issueToken($user, $request->string('device_name')->value());
        }

        throw ValidationException::withMessages([
            'code' => __('A two factor authentication code or recovery code is required.'),
        ]);
    }

    /**
     * A recovery code is single use, so it is replaced the moment it is spent.
     */
    private function consumeRecoveryCode(User $user, string $recoveryCode): void
    {
        $match = collect($user->recoveryCodes())
            ->first(fn (string $code): bool => hash_equals($code, $recoveryCode));

        if (! $match) {
            throw ValidationException::withMessages([
                'recovery_code' => __('The provided two factor authentication recovery code was invalid.'),
            ]);
        }

        $user->replaceRecoveryCode($match);
    }
}
