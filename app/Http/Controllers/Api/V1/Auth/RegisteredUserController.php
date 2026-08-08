<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Concerns\IssuesApiTokens;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    use IssuesApiTokens;

    /**
     * Register from the mobile app.
     *
     * Rules and side effects match the web controller exactly — same password
     * policy, same terms consent, same free-plan enrolment — so an account
     * created on a phone is indistinguishable from one created in a browser.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ], [
            'terms.accepted' => 'You must agree to the Terms and Conditions.',
        ]);

        $user = User::create([
            'name' => $request->string('name')->value(),
            'email' => $request->string('email')->value(),
            'password' => Hash::make($request->string('password')->value()),
        ]);

        event(new Registered($user));

        $user->subscribeToFreePlan();

        return $this->issueToken($user, $request->string('device_name')->value())
            ->setStatusCode(201);
    }
}
