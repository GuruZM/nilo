<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $companies = $user->companies()
            ->wherePivot('status', 'active') // keep if you use it
            ->select([
                'companies.id',
                'companies.name',
                'companies.email',
                'companies.phone',
                'companies.tpin',
                'companies.address',
                'companies.logo_path',
                'companies.primary_color',
            ])
            ->withCount(['clients'])
            ->withCount(['invoices as total_invoices'])
            ->withSum(['invoices as paid_revenue' => function ($q) {
                $q->where('status', 'paid');
            }], 'total')
            ->withSum(['invoices as pending_revenue' => function ($q) {
                $q->where('status', 'pending');
            }], 'total')
            ->orderBy('companies.name')
            ->get();

        // Prefer DB value; fallback to first company if null
        $activeCompanyId = $user->current_company_id ?? $companies->first()?->id;

        // Optional: if user has no current_company_id, set it once
        if (! $user->current_company_id && $activeCompanyId) {
            $user->forceFill(['current_company_id' => $activeCompanyId])->save();
        }

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'active_company_id' => $activeCompanyId,
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

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
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

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
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
