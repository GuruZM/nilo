<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyExchangeRateRequest;
use App\Http\Requests\UpdateActiveCurrenciesRequest;
use App\Models\CompanyExchangeRate;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateSynchronizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CurrencyController extends Controller
{
    /**
     * How long an on-demand sync locks out the next one, platform-wide.
     *
     * Upstream only republishes once every 24 hours, so a shorter window would
     * spend the provider's quota re-fetching numbers that cannot have moved.
     */
    protected const SYNC_COOLDOWN_SECONDS = 900;

    /**
     * Currencies CRUD page (global list)
     * URL: GET /settings/currencies
     */
    public function index(Request $request)
    {
        $currencies = Currency::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol', 'precision', 'is_active', 'created_at']);

        /**
         * Deliberately not called `currencies`: that name belongs to the
         * shared prop carrying the active set and current selection, and a
         * page prop of the same name shadows it, breaking the sidebar switcher.
         */
        return Inertia::render('settings/currencies', [
            'catalog' => $currencies,
            'fx' => $this->rateBoard($request),
        ]);
    }

    /**
     * The rate picture for the active company: what was synced, what the
     * company overrode, and which of the two is actually being used.
     *
     * @return array{
     *     base: string,
     *     rates_as_of: string|null,
     *     last_synced_at: string|null,
     *     rows: list<array{code: string, synced_rate: float|null, override_rate: float|null, override_set_at: string|null, in_force: bool}>
     * }
     */
    protected function rateBoard(Request $request): array
    {
        $companyId = $request->user()?->current_company_id;

        $synced = ExchangeRate::query()
            ->where('base_code', ExchangeRate::BASE)
            ->get(['quote_code', 'rate', 'updated_at'])
            ->keyBy(fn (ExchangeRate $rate) => strtoupper($rate->quote_code));

        $overrides = $companyId === null
            ? collect()
            : CompanyExchangeRate::query()
                ->where('company_id', $companyId)
                ->where('base_code', ExchangeRate::BASE)
                ->get(['quote_code', 'rate', 'set_at'])
                ->keyBy(fn (CompanyExchangeRate $rate) => strtoupper($rate->quote_code));

        $rows = Currency::query()
            ->where('is_active', true)
            ->where('code', '!=', ExchangeRate::BASE)
            ->orderBy('code')
            ->pluck('code')
            ->map(function (string $code) use ($synced, $overrides): array {
                $code = strtoupper($code);
                $syncedRate = $synced->get($code);
                $override = $overrides->get($code);

                return [
                    'code' => $code,
                    'synced_rate' => $syncedRate ? (float) $syncedRate->rate : null,
                    'override_rate' => $override ? (float) $override->rate : null,
                    'override_set_at' => $override?->set_at?->toIso8601String(),
                    'in_force' => $override !== null && (
                        $syncedRate === null
                        || $override->set_at->greaterThan($syncedRate->updated_at)
                    ),
                ];
            })
            ->values()
            ->all();

        return [
            'base' => ExchangeRate::BASE,
            'rates_as_of' => ExchangeRate::lastFetchedAt()?->toIso8601String(),
            'last_synced_at' => ExchangeRate::lastSyncedAt()?->toIso8601String(),
            'rows' => $rows,
        ];
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
     * Set which currencies are active, in bulk
     * URL: POST /currencies/active
     */
    public function updateActive(UpdateActiveCurrenciesRequest $request)
    {
        $codes = $request->codes();

        DB::transaction(function () use ($codes): void {
            Currency::query()->whereIn('code', $codes)->update(['is_active' => true]);
            Currency::query()->whereNotIn('code', $codes)->update(['is_active' => false]);
        });

        return back()->with('success', trans_choice(
            '{0}No currencies are active.|{1}1 currency active.|[2,*]:count currencies active.',
            count($codes),
            ['count' => count($codes)],
        ));
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

    /**
     * Set this company's manual rate for a currency.
     * URL: POST /currencies/{code}/rate
     */
    public function storeRate(StoreCompanyExchangeRateRequest $request)
    {
        $companyId = (int) $request->user()->current_company_id;

        CompanyExchangeRate::updateOrCreate(
            [
                'company_id' => $companyId,
                'base_code' => ExchangeRate::BASE,
                'quote_code' => $request->currencyCode(),
            ],
            [
                'rate' => $request->rate(),
                /** Refreshing `set_at` is what puts a superseded override back in force. */
                'set_at' => now(),
                'set_by' => $request->user()->id,
            ],
        );

        return back()->with('success', "Manual rate saved for {$request->currencyCode()}.");
    }

    /**
     * Clear this company's manual rate for a currency.
     * URL: DELETE /currencies/{code}/rate
     */
    public function destroyRate(Request $request, string $code)
    {
        CompanyExchangeRate::query()
            ->where('company_id', (int) $request->user()->current_company_id)
            ->where('base_code', ExchangeRate::BASE)
            ->where('quote_code', strtoupper($code))
            ->delete();

        return back()->with('success', 'Manual rate cleared.');
    }

    /**
     * Refresh the synced rates without waiting for the nightly schedule.
     * URL: POST /currencies/rates/sync
     *
     * Throttled platform-wide rather than per user: the table is shared, the
     * upstream feed only republishes once a day, and the provider's quota is
     * a single account's. The lock doubles as protection against a manual run
     * racing the scheduled one.
     */
    public function syncRates(Request $request, ExchangeRateSynchronizer $synchronizer)
    {
        $lock = Cache::lock('exchange-rates:sync', static::SYNC_COOLDOWN_SECONDS);

        if (! $lock->get()) {
            return back()->with('error', 'Rates were refreshed moments ago. Try again in a few minutes.');
        }

        $result = $synchronizer->sync();

        if (! $result->successful) {
            /**
             * A failed attempt should not burn the cooldown — the whole point
             * of the button is retrying when the provider was unreachable.
             */
            $lock->release();

            return back()->with('error', 'Could not reach the rate provider. Your existing rates were left unchanged.');
        }

        return back()->with('success', "{$result->count} rates refreshed.");
    }
}
