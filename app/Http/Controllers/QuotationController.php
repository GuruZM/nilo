<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Quotation;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
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
     * Display a listing of the quotations.
     */
    public function index(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            return Inertia::render('Quotations/Index', [
                'quotations' => [],
                'hasActiveCompany' => false,
            ]);
        }

        $quotations = collect();

        try {
            $quotations = Quotation::query()
                ->where('company_id', $companyId)
                ->with(['client:id,name'])
                ->orderByDesc('created_at')
                ->get([
                    'id',
                    'number',
                    'client_id',
                    'issue_date',
                    'valid_until',
                    'currency_code',
                    'total',
                    'status',
                    'created_at',
                ])
                ->map(fn (Quotation $quotation) => [
                    'id' => $quotation->id,
                    'number' => $quotation->number,
                    'client_id' => $quotation->client_id,
                    'client_name' => $quotation->client?->name,
                    'issue_date' => $quotation->issue_date,
                    'valid_until' => $quotation->valid_until,
                    'currency_code' => $quotation->currency_code,
                    'total' => (float) $quotation->total,
                    'status' => $quotation->status,
                    'created_at' => $quotation->created_at,
                ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '42P01') {
                throw $exception;
            }
        }

        return Inertia::render('Quotations/Index', [
            'quotations' => $quotations,
            'hasActiveCompany' => true,
        ]);
    }

    /**
     * Show the form for creating a new quotation.
     */
    public function create(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            return Inertia::render('Quotations/Create', [
                'clients' => [],
                'defaultCurrencyCode' => strtoupper((string) ($request->user()?->current_currency_code ?? '')),
                'hasActiveCompany' => false,
            ]);
        }

        $clients = Client::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'contact_person']);

        return Inertia::render('Quotations/Create', [
            'clients' => $clients,
            'defaultCurrencyCode' => strtoupper((string) ($request->user()?->current_currency_code ?? '')),
            'hasActiveCompany' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'status' => ['required', Rule::in(['draft', 'sent', 'accepted', 'expired'])],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'quotation_discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax' => ['nullable', 'numeric', 'min:0'],
        ]);

        $client = Client::query()
            ->where('id', (int) $data['client_id'])
            ->where('company_id', $companyId)
            ->first();

        if (! $client) {
            throw ValidationException::withMessages([
                'client_id' => 'That client is not in the active company.',
            ]);
        }

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $itemsToCreate = [];

        foreach ($data['items'] as $index => $row) {
            $quantity = (float) $row['quantity'];
            $unitPrice = (float) $row['unit_price'];
            $discount = (float) ($row['discount'] ?? 0);
            $tax = (float) ($row['tax'] ?? 0);

            $lineBase = $quantity * $unitPrice;
            $lineTotal = max(0, $lineBase - $discount + $tax);

            $itemsToCreate[] = [
                'description' => $row['description'],
                'unit' => $row['unit'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'tax' => $tax,
                'line_total' => $lineTotal,
                'sort_order' => $index,
            ];

            $subtotal += $lineBase;
            $discountTotal += $discount;
            $taxTotal += $tax;
        }

        $quotationDiscount = (float) ($data['quotation_discount'] ?? 0);
        $total = max(0, $subtotal - $discountTotal - $quotationDiscount + $taxTotal);

        try {
            DB::transaction(function () use (
                $companyId,
                $request,
                $data,
                $subtotal,
                $discountTotal,
                $quotationDiscount,
                $taxTotal,
                $total,
                $itemsToCreate
            ): void {
                $sequence = Quotation::query()
                    ->where('company_id', $companyId)
                    ->count() + 1;

                $quotation = Quotation::query()->create([
                    'company_id' => $companyId,
                    'client_id' => (int) $data['client_id'],
                    'created_by' => $request->user()?->id,
                    'number' => 'QUO-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                    'reference' => $data['reference'] ?? null,
                    'title' => $data['title'] ?? null,
                    'issue_date' => $data['issue_date'],
                    'valid_until' => $data['valid_until'] ?? null,
                    'currency_code' => strtoupper((string) $data['currency_code']),
                    'subtotal' => $subtotal,
                    'discount_total' => $discountTotal + $quotationDiscount,
                    'tax_total' => $taxTotal,
                    'total' => $total,
                    'status' => $data['status'],
                    'notes' => $data['notes'] ?? null,
                    'terms' => $data['terms'] ?? null,
                ]);

                $quotation->items()->createMany($itemsToCreate);
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '42P01') {
                return back()
                    ->withErrors([
                        'quotation' => 'Quotation storage is not set up yet. Run the quotations migrations first.',
                    ])
                    ->withInput();
            }

            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Quotation creation failed', [
                'company_id' => $companyId,
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withErrors([
                    'quotation' => 'Failed to create quotation. Please try again.',
                ])
                ->withInput();
        }

        return redirect()
            ->route('quotations.index')
            ->with('success', 'Quotation created.');
    }
}
