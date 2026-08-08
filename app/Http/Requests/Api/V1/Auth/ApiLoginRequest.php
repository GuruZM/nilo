<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Auth\LoginRequest;

/**
 * The web login rules plus the device label a token is filed under.
 *
 * Extending rather than rewriting keeps `validateCredentials()` and its
 * five-attempts-per-minute throttle shared, so a password cannot be brute
 * forced faster through the API than through the browser.
 */
class ApiLoginRequest extends LoginRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
