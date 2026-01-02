<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class CompanyController extends Controller
{
    
     public function index(Request $request)
{
    $user = $request->user();

    $companies = $user->companies()
        ->wherePivot('status', 'active') // optional, only if you use status
        ->get(['companies.id', 'companies.name']);

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
        ]);

        // Optional: prevent duplicate company names per owner (you can relax this later)
        // If you don't want this rule, remove this check.
        $exists = \App\Models\Company::where('owner_id', $user->id)
            ->whereRaw('LOWER(name) = LOWER(?)', [$data['name']])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'name' => 'You already have a company with this name.',
            ]);
        }

        $company = \App\Models\Company::create([
            'owner_id' => $user->id,
            'name' => $data['name'],
            'slug' => \App\Support\Slug::uniqueCompanySlug($data['name']),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'tpin' => $data['tpin'] ?? null,
            'address' => $data['address'] ?? null,
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

        // Return a normal redirect so Inertia can refresh props
        return back()->with('success', 'Company created successfully.');
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Let Inertia receive field-level errors (422)
        throw $e;
    } catch (\Throwable $e) {
        \Log::error('Company creation failed', [
            'user_id' => $user?->id,
            'error' => $e->getMessage(),
        ]);

        // Non-validation failure -> show a friendly error
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
    public function update(Request $request, Company $company)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        //
    }
}
