<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Concerns\IssuesApiTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ApiLoginRequest;
use App\Support\TwoFactorChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;

class AuthenticatedSessionController extends Controller
{
    use IssuesApiTokens;

    /**
     * Exchange credentials for a bearer token.
     *
     * Mirrors the web controller's shape, including its refusal to log anyone
     * in before the second factor is cleared. Where the browser parks the
     * half-authenticated user in the session, this hands back a short-lived
     * sealed challenge instead — the client holds it, nothing is stored here.
     */
    public function store(ApiLoginRequest $request): JsonResponse
    {
        $user = $request->validateCredentials();

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'two_factor' => true,
                'challenge' => TwoFactorChallenge::issue($user),
            ], 202);
        }

        return $this->issueToken($user, $request->string('device_name')->value());
    }

    /**
     * Sign this device out, leaving every other device signed in.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    /**
     * Sign every device out — the "lost my phone" button.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Signed out of every device.']);
    }
}
