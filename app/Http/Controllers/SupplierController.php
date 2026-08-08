<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    private function resolveCompanyId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $companyId = (int) ($user->current_company_id ?? 0);

        if ($companyId && $user->isMemberOfCompany($companyId)) {
            return $companyId;
        }

        $fallbackCompanyId = (int) ($user->companies()
            ->orderBy('companies.name')
            ->value('companies.id') ?? 0);

        if ($fallbackCompanyId) {
            $user->forceFill(['current_company_id' => $fallbackCompanyId])->save();
        }

        return $fallbackCompanyId ?: null;
    }

    private function companyId(Request $request): int
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected.',
            ]);
        }

        return $companyId;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'tpin' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'contact_person' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function index(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $companyId
                ? Supplier::query()
                    ->where('company_id', $companyId)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'phone', 'tpin', 'address', 'city', 'country', 'contact_person', 'notes'])
                : [],
            'hasActiveCompany' => (bool) $companyId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);

        $supplier = Supplier::query()->create(
            $request->validate($this->rules()) + ['company_id' => $companyId]
        );

        return back()
            ->with('success', 'Supplier added.')
            ->with('created_supplier_id', $supplier->id);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless((int) $supplier->company_id === $this->companyId($request), 403);

        $supplier->update($request->validate($this->rules()));

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless((int) $supplier->company_id === $this->companyId($request), 403);

        $supplier->delete();

        return back()->with('success', 'Supplier removed.');
    }
}
