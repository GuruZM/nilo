<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

trait IssuesApiTokens
{
    /**
     * The one place a bearer token is minted, so every front door hands back
     * the same envelope and names the device the same way.
     */
    protected function issueToken(User $user, ?string $deviceName): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken($deviceName ?: 'mobile')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }
}
