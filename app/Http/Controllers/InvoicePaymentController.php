<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\DocumentRenderer;
use App\Services\InvoiceSettlement;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Money received against an invoice: recorded, removed, and printed as the
 * client's receipt.
 *
 * The invoice status is never written here — {@see InvoiceSettlement} derives
 * it from the ledger, so recording and removing cannot disagree about whether
 * an invoice is settled.
 */
class InvoicePaymentController extends Controller
{
    public function __construct(
        private InvoiceSettlement $settlement,
        private DocumentRenderer $documents,
    ) {}

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
     * Route model binding resolves a payment by id alone, so a row from another
     * company binds perfectly well. Both halves are checked here: the invoice
     * must be in the active company, and the payment must belong to that
     * invoice — otherwise one company's receipt could be printed through
     * another company's invoice.
     */
    private function guardPayment(Request $request, Invoice $invoice, InvoicePayment $payment): void
    {
        abort_unless((int) $invoice->company_id === $this->companyId($request), 403);
        abort_unless((int) $payment->invoice_id === (int) $invoice->id, 403);
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $invoice->company_id === $companyId, 403);

        $balance = $this->settlement->balanceDue($invoice);

        $data = $request->validate([
            /**
             * `numeric` makes `max` a value comparison rather than a length
             * one, so this caps the payment at what is still owed. On a fully
             * settled invoice the cap is `max:0` and every payment is refused,
             * which is the correct outcome — the message below says so in
             * words rather than quoting a zero.
             */
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$balance],
            'paid_on' => ['required', 'date'],
            'method' => ['required', Rule::in(InvoicePayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:190'],
        ], [
            'amount.max' => $balance > 0
                ? 'That is more than the '.number_format($balance, 2, '.', ',').' '
                    .$invoice->currency_code.' still outstanding on this invoice.'
                : 'Invoice '.$invoice->number.' is already settled in full.',
        ]);

        DB::transaction(function () use ($companyId, $request, $invoice, $data): void {
            $payment = InvoicePayment::query()->create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'recorded_by' => $request->user()?->id,
                'receipt_number' => DocumentNumber::nextFor(
                    InvoicePayment::class,
                    $companyId,
                    DocumentType::Receipt,
                ),
                'amount' => (float) $data['amount'],

                /** Never the request's — a receipt is denominated by its invoice. */
                'currency_code' => $invoice->currency_code,

                'paid_on' => $data['paid_on'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
            ]);

            /**
             * Order matters. The payment row must exist before either of the
             * next two steps, because both read the ledger: `sync` to derive
             * the status, `balanceDue` to get the figure this receipt will
             * carry for ever. Stamping is last and writes only `balance_after`,
             * a column neither of them reads, so it cannot disturb what they
             * just computed and needs no second sync.
             */
            $invoice = $invoice->fresh();

            $this->settlement->sync($invoice);

            $payment->update([
                'balance_after' => $this->settlement->balanceDue($invoice),
            ]);
        });

        return back()->with('success', 'Payment recorded.');
    }

    public function destroy(Request $request, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $this->guardPayment($request, $invoice, $payment);

        DB::transaction(function () use ($invoice, $payment): void {
            $payment->delete();

            $this->settlement->sync($invoice->fresh());
        });

        return back()->with('success', 'Payment removed.');
    }

    public function print(Request $request, Invoice $invoice, InvoicePayment $payment)
    {
        $this->guardPayment($request, $invoice, $payment);

        return response()->view(
            'invoices.templates.default',
            $this->documents->viewData($payment, 'print') + [
                'autoPrint' => $request->boolean('download'),
            ]
        );
    }
}
