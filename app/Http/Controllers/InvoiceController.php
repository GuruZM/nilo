<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    private function companyId(Request $request): int
    {
        $companyId = (int) ($request->user()?->current_company_id ?? 0);

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected.',
            ]);
        }

        return $companyId;
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId($request);

        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->with(['items:id,invoice_id,line_total',])
            ->orderByDesc('created_at')
            ->get([
                'id','number','client_id','issue_date','due_date','currency_code','total','status','is_recurring','created_at'
            ]);

        // Minimal client list for display
        $clients = Client::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id','name']);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'clients' => $clients,
        ]);
    }

    public function create(Request $request)
    {
        $companyId = $this->companyId($request);

        $clients = Client::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id','name','email','contact_person']);

        $templates = InvoiceTemplate::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id','name','is_default']);

        // currency default: use shared active currency if present, else ZMW
        $activeCurrencyCode = strtoupper((string) ($request->user()?->current_currency_code ?? 'ZMW'));

        return Inertia::render('Invoices/Create', [
            'clients' => $clients,
            'templates' => $templates,
            'defaultCurrencyCode' => $activeCurrencyCode,
        ]);
    }

    public function store(Request $request)
{
    $companyId = $this->companyId($request);
    $user = $request->user();

    try {
        $data = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'invoice_template_id' => ['nullable', 'integer', Rule::exists('invoice_templates', 'id')],

            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],

            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],

            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],

            'has_delivery_note' => ['required', 'boolean'],

            // ✅ NEW: pending/paid (default handled by UI, but validate anyway)
            'status' => ['required', 'string', Rule::in(['pending', 'paid'])],

            'is_recurring' => ['required', 'boolean'],
            'recurrence_frequency' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurrence_start_date' => ['nullable', 'date'],
            'recurrence_end_date' => ['nullable', 'date', 'after_or_equal:recurrence_start_date'],

            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],

            // ✅ NEW: overall invoice discount
            'invoice_discount' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax' => ['nullable', 'numeric', 'min:0'],
        ]);

        // ✅ Ensure client belongs to active company
        $clientOk = Client::query()
            ->where('id', (int) $data['client_id'])
            ->where('company_id', $companyId)
            ->exists();

        if (! $clientOk) {
            throw ValidationException::withMessages([
                'client_id' => 'That client is not in the active company.',
            ]);
        }

        // ✅ Ensure template belongs to active company (if provided)
        if (! empty($data['invoice_template_id'])) {
            $tplOk = InvoiceTemplate::query()
                ->where('id', (int) $data['invoice_template_id'])
                ->where('company_id', $companyId)
                ->exists();

            if (! $tplOk) {
                throw ValidationException::withMessages([
                    'invoice_template_id' => 'That template is not in the active company.',
                ]);
            }
        }

        // ✅ Recurring validation rules
        if ((bool) $data['is_recurring']) {
            if (empty($data['recurrence_frequency'])) {
                throw ValidationException::withMessages([
                    'recurrence_frequency' => 'Select a recurrence frequency.',
                ]);
            }

            // If interval omitted, set default
            if (empty($data['recurrence_interval'])) {
                $data['recurrence_interval'] = 1;
            }

            // next_run_at: later we’ll calculate properly (cron), for now anchor it
            $data['next_run_at'] = $data['recurrence_start_date'] ?? $data['issue_date'];
        } else {
            $data['recurrence_frequency'] = null;
            $data['recurrence_interval'] = null;
            $data['recurrence_start_date'] = null;
            $data['recurrence_end_date'] = null;
            $data['next_run_at'] = null;
        }

        // ✅ Compute totals (includes overall invoice_discount)
        $subtotal = 0.0;
        $lineDiscountTotal = 0.0;
        $taxTotal = 0.0;

        $items = $data['items'];

        foreach ($items as $i => $row) {
            $qty = (float) $row['quantity'];
            $price = (float) $row['unit_price'];
            $disc = (float) ($row['discount'] ?? 0);
            $tax = (float) ($row['tax'] ?? 0);

            $lineBase = $qty * $price;
            $lineTotal = max(0, $lineBase - $disc + $tax);

            $items[$i]['discount'] = $disc;
            $items[$i]['tax'] = $tax;
            $items[$i]['line_total'] = $lineTotal;
            $items[$i]['sort_order'] = $i;

            $subtotal += $lineBase;
            $lineDiscountTotal += $disc;
            $taxTotal += $tax;
        }

        $invoiceDiscount = (float) ($data['invoice_discount'] ?? 0);
        $discountTotal = $lineDiscountTotal + $invoiceDiscount;
        $total = max(0, $subtotal - $discountTotal + $taxTotal);

        $invoice = DB::transaction(function () use (
            $companyId,
            $user,
            $data,
            $items,
            $subtotal,
            $lineDiscountTotal,
            $invoiceDiscount,
            $discountTotal,
            $taxTotal,
            $total
        ) {
            $invoice = Invoice::create([
                'company_id' => $companyId,
                'client_id' => (int) $data['client_id'],
                'invoice_template_id' => $data['invoice_template_id'] ?? null,
                'created_by' => $user?->id,

                'number' => null, // next: INV numbering (per company)
                'reference' => $data['reference'] ?? null,
                'title' => $data['title'] ?? null,

                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,

                'currency_code' => strtoupper($data['currency_code']),

                'subtotal' => $subtotal,

                // ✅ keep both so UI can show breakdown nicely
                'line_discount_total' => $lineDiscountTotal,     // add column if you want this
                'invoice_discount' => $invoiceDiscount,          // add column if you want this
                'discount_total' => $discountTotal,

                'tax_total' => $taxTotal,
                'total' => $total,

                // ✅ NEW: pending/paid
                'status' => $data['status'],

                'has_delivery_note' => (bool) $data['has_delivery_note'],

                'is_recurring' => (bool) $data['is_recurring'],
                'recurrence_frequency' => $data['recurrence_frequency'] ?? null,
                'recurrence_interval' => $data['recurrence_interval'] ?? null,
                'recurrence_start_date' => $data['recurrence_start_date'] ?? null,
                'recurrence_end_date' => $data['recurrence_end_date'] ?? null,
                'next_run_at' => $data['next_run_at'] ?? null,

                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            foreach ($items as $row) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $row['description'],
                    'unit' => $row['unit'] ?? null,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'discount' => $row['discount'] ?? 0,
                    'tax' => $row['tax'] ?? 0,
                    'line_total' => $row['line_total'],
                    'sort_order' => $row['sort_order'] ?? 0,
                ]);
            }

            return $invoice;
        });

        // ✅ Inertia-friendly redirect with flash (this is what makes onSuccess + global flash work)
        return redirect("/invoices/{$invoice->id}")
            ->with('success', 'Invoice created.');
    } catch (ValidationException $e) {
        throw $e;
    } catch (\Throwable $e) {
        Log::error('Invoice store failed', [
            'user_id' => $user?->id,
            'company_id' => $companyId,
            'error' => $e->getMessage(),
        ]);

        throw ValidationException::withMessages([
            'invoice' => 'Failed to create invoice. Please try again.',
        ]);
    }
}

    public function show(Request $request, Invoice $invoice)
    {
        $companyId = $this->companyId($request);

        if ((int) $invoice->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice not found for the active company.',
            ]);
        }

        $invoice->load(['items',]);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    // edit/update/destroy next — we’ll wire after Create is perfect
}