<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CurrencyController extends Controller
{
    /**
     * Currencies CRUD page (global list)
     * URL: GET /settings/currencies
     */
    public function index(Request $request)
    {
        $currencies = Currency::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol', 'precision', 'is_active', 'created_at']);

        return Inertia::render('settings/currencies', [
            'currencies' => $currencies,
        ]);
    }

    /**
     * Create currency
     * URL: POST /currencies
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'code' => ['required', 'string', 'size:3', 'alpha', Rule::unique('currencies', 'code')],
                'name' => ['required', 'string', 'max:120'],
                'symbol' => ['nullable', 'string', 'max:10'],
                'precision' => ['required', 'integer', 'min:0', 'max:6'],
                'is_active' => ['required', 'boolean'],
            ]);

            $data['code'] = strtoupper($data['code']);

            Currency::create($data);

            return back()->with('success', 'Currency added.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Currency store failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'code' => 'Failed to add currency. Please try again.',
            ]);
        }
    }

    /**
     * Update currency
     * URL: PUT /currencies/{currency}
     */
    public function update(Request $request, Currency $currency)
    {
        try {
            $data = $request->validate([
                'code' => [
                    'required',
                    'string',
                    'size:3',
                    'alpha',
                    Rule::unique('currencies', 'code')->ignore($currency->id),
                ],
                'name' => ['required', 'string', 'max:120'],
                'symbol' => ['nullable', 'string', 'max:10'],
                'precision' => ['required', 'integer', 'min:0', 'max:6'],
                'is_active' => ['required', 'boolean'],
            ]);

            $data['code'] = strtoupper($data['code']);

            // Prevent deactivating currently-active currency
            $user = $request->user();
            if (
                $user &&
                strtoupper((string) $user->current_currency_code) === strtoupper((string) $currency->code) &&
                ! $data['is_active']
            ) {
                throw ValidationException::withMessages([
                    'is_active' => 'You cannot deactivate your currently active currency. Switch currency first.',
                ]);
            }

            $oldCode = $currency->code;

            $currency->update($data);

            // If currency code changed and user had it active, update user
            if ($user && strtoupper((string) $user->current_currency_code) === strtoupper((string) $oldCode)) {
                $user->forceFill(['current_currency_code' => $currency->code])->save();
            }

            return back()->with('success', 'Currency updated.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Currency update failed', [
                'user_id' => $request->user()?->id,
                'currency_id' => $currency->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'code' => 'Failed to update currency. Please try again.',
            ]);
        }
    }

    /**
     * Delete currency
     * URL: DELETE /currencies/{currency}
     */
    public function destroy(Request $request, Currency $currency)
    {
        try {
            $user = $request->user();

            if ($user && strtoupper((string) $user->current_currency_code) === strtoupper((string) $currency->code)) {
                throw ValidationException::withMessages([
                    'currency' => 'You cannot delete your currently active currency. Switch currency first.',
                ]);
            }

            $currency->delete();

            return back()->with('success', 'Currency deleted.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Currency delete failed', [
                'user_id' => $request->user()?->id,
                'currency_id' => $currency->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'currency' => 'Failed to delete currency. Please try again.',
            ]);
        }
    }

    /**
     * Switch active currency (like active company)
     * URL: POST /currencies/switch
     */
    public function switch(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
        ]);

        $code = strtoupper($data['currency_code']);

        // Throw error (422) if already active (as requested)
        if (strtoupper((string) $user->current_currency_code) === $code) {
            throw ValidationException::withMessages([
                'currency_code' => 'That currency is already active.',
            ]);
        }

        // Must be active
        $isActive = Currency::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->exists();

        if (! $isActive) {
            throw ValidationException::withMessages([
                'currency_code' => 'That currency is not active.',
            ]);
        }

        $user->forceFill(['current_currency_code' => $code])->save();

        return back()->with('success', 'Currency switched.');
    }
}