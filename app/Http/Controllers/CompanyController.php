<?php

namespace App\Http\Controllers;

use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\CurrencyRollup;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CompanyController extends Controller
{
    /**
     * Currency codes are stored uppercase, and the `exists` rule is
     * case-sensitive, so normalize before validating.
     */
    private function normalizeCurrencyCode(Request $request): void
    {
        $currencyCode = $request->input('currency_code');

        if (is_string($currencyCode)) {
            $request->merge(['currency_code' => strtoupper(trim($currencyCode))]);
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $companies = $user->companies()
            ->wherePivot('status', 'active') // keep if you use it
            ->select([
                'companies.id',
                'companies.name',
                'companies.type',
                'companies.currency_code',
                'companies.email',
                'companies.phone',
                'companies.tpin',
                'companies.address',
                'companies.logo_path',
                'companies.primary_color',
            ])
            ->withCount(['clients'])
            ->withCount(['invoices as total_invoices'])
            ->with(['documents' => fn ($query) => $query->latest()])
            ->orderBy('companies.name')
            ->get();

        /**
         * Revenue is summed per currency and converted, because a company can
         * hold invoices issued in currencies other than the one it bills in.
         */
        $displayCode = $user->displayCurrencyCode();

        /**
         * Each company converts through its own rate overrides, so a row is
         * converted by its company's rollup rather than one shared instance.
         * The bare rollup below carries the page-level meta and gathers the
         * gaps back together.
         */
        $rollup = CurrencyRollup::into($displayCode);

        $rollups = $companies->mapWithKeys(fn (Company $company) => [
            $company->id => CurrencyRollup::into($displayCode, $company->id),
        ]);

        /**
         * Summed per invoice rather than per currency, because an outstanding
         * invoice contributes only what is still owed on it and that has to be
         * netted off before conversion. Grouping in SQL cannot see the payment
         * ledger, and a company whose card disagreed with its own dashboard
         * would be worse than a slower query.
         */
        $revenueByCompany = Invoice::query()
            ->whereIn('company_id', $companies->pluck('id'))
            ->whereIn('status', [Invoice::STATUS_PAID, ...Invoice::OUTSTANDING_STATUSES])
            ->withSum('payments as paid_sum', 'amount')
            ->get(['id', 'company_id', 'currency_code', 'status', 'total'])
            ->reduce(function (array $carry, Invoice $invoice) use ($rollups, $rollup): array {
                /**
                 * Every unpaid status collapses into one outstanding bucket, so
                 * an emailed invoice is not grouped under a key nothing reads.
                 */
                $isPaid = $invoice->status === Invoice::STATUS_PAID;

                $amount = $isPaid
                    ? (float) $invoice->total
                    : max(0, (float) $invoice->total - (float) ($invoice->paid_sum ?? 0));

                $converted = ($rollups[$invoice->company_id] ?? $rollup)
                    ->convert($amount, (string) $invoice->currency_code);

                $key = "{$invoice->company_id}.".($isPaid ? 'paid' : 'pending');
                $carry[$key] = ($carry[$key] ?? 0.0) + $converted;

                return $carry;
            }, []);

        $rollups->each(fn (CurrencyRollup $each) => $rollup->absorbUnconvertible($each));

        $companies->each(function (Company $company) use ($revenueByCompany): void {
            $company->paid_revenue = $revenueByCompany["{$company->id}.paid"] ?? 0.0;
            $company->pending_revenue = $revenueByCompany["{$company->id}.pending"] ?? 0.0;
            $company->append('profile_completion');
        });

        // Prefer DB value; fallback to first company if null
        $activeCompanyId = $user->current_company_id ?? $companies->first()?->id;

        // Optional: if user has no current_company_id, set it once
        if (! $user->current_company_id && $activeCompanyId) {
            $user->forceFill(['current_company_id' => $activeCompanyId])->save();
        }

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'active_company_id' => $activeCompanyId,
            'fx' => $rollup->meta(),
            'limitNotice' => $request->session()->get('limit_notice'),
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $user = $request->user();

        // ensure user belongs to company
        $belongs = $user->companies()->whereKey($company->id)->exists();
        if (! $belongs) {
            throw ValidationException::withMessages([
                'company' => 'You are not allowed to update this company.',
            ]);
        }

        $this->normalizeCurrencyCode($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'type' => ['required', Rule::enum(CompanyType::class)],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')->where('is_active', true)],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tpin' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:190'],
            'primary_color' => ['nullable', 'string', 'max:20'],

            // ✅ logo upload
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        // Remove logo if requested
        if (! empty($data['remove_logo'])) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $company->logo_path = null;
        }

        // Upload logo if present
        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }

            $path = $request->file('logo')->store('company-logos', 'public');
            $company->logo_path = $path;
        }

        $company->fill([
            'name' => $data['name'],
            'type' => $data['type'],
            'currency_code' => strtoupper($data['currency_code']),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'tpin' => $data['tpin'] ?? null,
            'address' => $data['address'] ?? null,
            'primary_color' => $data['primary_color'] ?? $company->primary_color,
        ])->save();

        return back()->with('success', 'Company updated.');
    }

    /**
     * Switch the active company for the user (store in session).
     */
    public function switch(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
        ]);

        $companyId = (int) $data['company_id'];

        try {
            $isMember = $user->companies()
                ->where('companies.id', $companyId)
                // ->wherePivot('status', 'active') // enable only if pivot has this + data
                ->exists();

            if (! $isMember) {
                throw ValidationException::withMessages([
                    'company_id' => 'You are not authorized to switch to this company.',
                ]);
            }

            // ✅ Treat "already active" as an error (422)
            if ((int) $user->current_company_id === $companyId) {
                throw ValidationException::withMessages([
                    'company_id' => 'That company is already active.',
                ]);
            }

            $user->forceFill(['current_company_id' => $companyId])->save();

            return back()->with('success', 'Active company switched.');
        } catch (ValidationException $e) {
            throw $e; // Inertia gets 422 + errors => triggers onError
        } catch (\Throwable $e) {
            \Log::error('Company switch failed', [
                'user_id' => $user?->id,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'company_id' => 'Failed to switch company. Please try again.',
            ]);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $limiter = new SubscriptionLimitService($user);

        /**
         * A refusal here is a plain redirect with no validation errors, which
         * Inertia reports to the client as a success. It has to arrive as
         * something the page renders, or the dialog closes claiming the company
         * was created when nothing was written at all.
         */
        if (! $limiter->canCreateCompany()) {
            return back()->with('limit_notice', $limiter->limitNotice('companies'));
        }

        $this->normalizeCurrencyCode($request);

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'type' => ['required', Rule::enum(CompanyType::class)],
                'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')->where('is_active', true)],
                'email' => ['nullable', 'email', 'max:120'],
                'phone' => ['nullable', 'string', 'max:40'],
                'tpin' => ['nullable', 'string', 'max:30'],
                'address' => ['nullable', 'string', 'max:255'],

                'primary_color' => ['nullable', 'string', 'max:30'],

                // ✅ logo upload
                'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            ]);

            // Optional duplicate name check per owner
            $exists = \App\Models\Company::where('owner_id', $user->id)
                ->whereRaw('LOWER(name) = LOWER(?)', [$data['name']])
                ->exists();

            if ($exists) {
                return back()->withErrors([
                    'name' => 'You already have a company with this name.',
                ]);
            }

            // ✅ Store logo (if provided)
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('company-logos', 'public');
            }

            $company = \App\Models\Company::create([
                'owner_id' => $user->id,
                'name' => $data['name'],
                'slug' => \App\Support\Slug::uniqueCompanySlug($data['name']),
                'type' => $data['type'],
                'currency_code' => strtoupper($data['currency_code']),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'tpin' => $data['tpin'] ?? null,
                'address' => $data['address'] ?? null,

                'primary_color' => $data['primary_color'] ?? null,
                'logo_path' => $logoPath,
            ]);

            // Attach membership (owner)
            $company->users()->attach($user->id, [
                'is_owner' => true,
                'status' => 'active',
            ]);

            // Set as current company if none selected yet
            if (! $user->current_company_id) {
                $user->forceFill(['current_company_id' => $company->id])->save();
            }

            return back()->with('success', 'Company created successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Company creation failed', [
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'name' => 'Failed to create company. Please try again.',
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        //
    }
}
