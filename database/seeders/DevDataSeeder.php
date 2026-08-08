<?php

namespace Database\Seeders;

use App\Enums\CompanyType;
use App\Enums\DocumentType;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Documents\IssueReceipt;
use App\Services\InvoiceSettlement;
use App\Services\TemplateProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A working local dataset covering every document Nilo issues.
 *
 * Development only — it writes plausible trading history, not fixtures any test
 * depends on. Tests build their own state through factories, so nothing here is
 * load-bearing and it is safe to change.
 *
 * Deliberately idempotent by company slug: running it twice adds a second
 * company rather than duplicating documents inside the first, so it can be
 * re-run against a database that already has real data in it without touching
 * what is already there.
 */
class DevDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstWhere('email', 'test@example.com')
            ?? User::query()->first();

        if (! $user) {
            $this->command?->warn('No user to attach a company to. Run DatabaseSeeder first.');

            return;
        }

        $company = $this->companyFor($user);
        $this->templatesFor($company);

        [$acme, $lusakaCo, $riverside] = $this->clientsFor($company);
        $supplier = $this->supplierFor($company);

        $this->settledInvoice($company, $acme);
        $this->partlyPaidInvoice($company, $lusakaCo);
        $this->creditedInvoice($company, $riverside);
        $this->overdueInvoice($company, $acme);
        $this->deliveredInvoice($company, $lusakaCo);

        $this->quotationFor($company, $riverside);
        $this->purchaseOrderFor($company, $supplier);
        $this->standaloneReceiptFor($company, $riverside);

        $this->command?->info("Seeded dev data into company [{$company->name}] for {$user->email}.");
    }

    private function companyFor(User $user): Company
    {
        $company = Company::query()->create([
            'owner_id' => $user->id,
            'name' => 'Nilo Demo Trading',
            'slug' => 'nilo-demo-'.Str::lower(Str::random(6)),
            'type' => CompanyType::Products,
            'currency_code' => 'ZMW',
            'email' => 'accounts@nilo-demo.test',
            'phone' => '+260 971 000 000',
            'tpin' => '1002003004',
            'address' => '12 Cairo Road, Lusaka',
        ]);

        $user->companies()->syncWithoutDetaching([
            $company->id => ['is_owner' => true, 'status' => 'active'],
        ]);

        $user->forceFill(['current_company_id' => $company->id])->save();

        return $company;
    }

    /**
     * Every type gets a template up front so the demo never shows a
     * first-use provisioning side effect mid-browse.
     */
    private function templatesFor(Company $company): void
    {
        $provisioner = app(TemplateProvisioner::class);

        foreach (DocumentType::cases() as $type) {
            $provisioner->forCompany($company->id, $type);
        }
    }

    /**
     * @return array{0: Client, 1: Client, 2: Client}
     */
    private function clientsFor(Company $company): array
    {
        return [
            Client::query()->create([
                'company_id' => $company->id,
                'name' => 'Acme Ltd',
                'email' => 'ap@acme.test',
                'phone' => '+260 966 111 222',
                'tpin' => '2003004005',
                'address' => '4 Great East Road',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'contact_person' => 'M. Banda',
            ]),
            Client::query()->create([
                'company_id' => $company->id,
                'name' => 'Lusaka Consolidated',
                'email' => 'finance@lusakaco.test',
                'phone' => '+260 977 333 444',
                'address' => '88 Cha Cha Cha Road',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'contact_person' => 'P. Mwale',
            ]),
            Client::query()->create([
                'company_id' => $company->id,
                'name' => 'Riverside Lodge',
                'email' => 'bookings@riverside.test',
                'city' => 'Livingstone',
                'country' => 'Zambia',
                'contact_person' => 'C. Zulu',
            ]),
        ];
    }

    private function supplierFor(Company $company): Supplier
    {
        return Supplier::query()->create([
            'company_id' => $company->id,
            'name' => 'Zambezi Steel',
            'email' => 'sales@zambezisteel.test',
            'phone' => '+260 955 777 888',
            'tpin' => '3004005006',
            'address' => 'Plot 9, Heavy Industrial Area',
            'city' => 'Ndola',
            'country' => 'Zambia',
            'contact_person' => 'T. Phiri',
        ]);
    }

    /**
     * @param  array<int, array{description: string, unit: string, quantity: float, unit_price: float}>  $lines
     */
    private function invoice(
        Company $company,
        Client $client,
        string $number,
        array $lines,
        string $status,
        int $issuedDaysAgo,
        int $dueInDays,
    ): Invoice {
        $gross = array_reduce(
            $lines,
            fn (float $carry, array $line) => $carry + ($line['quantity'] * $line['unit_price']),
            0.0,
        );

        /** Prices are tax inclusive, so the tax is carved back out of the total. */
        $total = round($gross, 2);
        $subtotal = round($total / 1.16, 2);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => $number,
            'issue_date' => now()->subDays($issuedDaysAgo)->toDateString(),
            'due_date' => now()->subDays($issuedDaysAgo)->addDays($dueInDays)->toDateString(),
            'currency_code' => 'ZMW',
            'subtotal' => $subtotal,
            'tax_percent' => 16,
            'tax_total' => round($total - $subtotal, 2),
            'total' => $total,
            'status' => $status,
        ]);

        foreach ($lines as $index => $line) {
            $invoice->items()->create([
                'description' => $line['description'],
                'unit' => $line['unit'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => round($line['quantity'] * $line['unit_price'], 2),
                'sort_order' => $index,
            ]);
        }

        return $invoice;
    }

    /**
     * Records a payment the way the controller does, including the balance
     * frozen onto the receipt, so the seeded receipts print honestly.
     */
    private function pay(Invoice $invoice, float $amount, string $method, int $daysAgo, ?string $reference = null): void
    {
        $settlement = app(InvoiceSettlement::class);

        $payment = InvoicePayment::query()->create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'receipt_number' => \App\Support\DocumentNumber::nextFor(
                InvoicePayment::class,
                (int) $invoice->company_id,
                DocumentType::Receipt,
                'receipt_number',
            ),
            'amount' => $amount,
            'currency_code' => $invoice->currency_code,
            'paid_on' => now()->subDays($daysAgo)->toDateString(),
            'method' => $method,
            'reference' => $reference,
        ]);

        $settlement->sync($invoice->fresh());
        $payment->update(['balance_after' => $settlement->balanceDue($invoice->fresh())]);
    }

    /**
     * A deposit taken before any invoice was raised, so the register shows both
     * kinds of receipt side by side. Seeded last on purpose: its number falls
     * after the invoice-backed ones, which is the shared sequence working.
     */
    private function standaloneReceiptFor(Company $company, Client $client): void
    {
        app(IssueReceipt::class)->handle((int) $company->id, null, [
            'client_id' => $client->id,
            'amount' => 7500,
            'currency_code' => $company->currency_code,
            'paid_on' => now()->subDays(5)->toDateString(),
            'method' => 'cash',
            'reference' => null,
            'description' => 'Deposit on borehole installation',
        ]);
    }

    private function settledInvoice(Company $company, Client $client): void
    {
        $invoice = $this->invoice($company, $client, 'INV-000001', [
            ['description' => 'Steel bolts M12', 'unit' => 'box', 'quantity' => 4, 'unit_price' => 750],
            ['description' => 'Washers', 'unit' => 'box', 'quantity' => 2, 'unit_price' => 1000],
        ], 'sent', issuedDaysAgo: 40, dueInDays: 14);

        $this->pay($invoice, 3000, 'bank_transfer', daysAgo: 32, reference: 'FT2607·8841');
        $this->pay($invoice, 2000, 'mobile_money', daysAgo: 26, reference: 'MM·55210');
    }

    private function partlyPaidInvoice(Company $company, Client $client): Invoice
    {
        $invoice = $this->invoice($company, $client, 'INV-000002', [
            ['description' => 'Site survey', 'unit' => 'day', 'quantity' => 3, 'unit_price' => 2500],
            ['description' => 'Report preparation', 'unit' => 'ea', 'quantity' => 1, 'unit_price' => 4500],
        ], 'sent', issuedDaysAgo: 18, dueInDays: 30);

        $this->pay($invoice, 4000, 'cash', daysAgo: 9);

        return $invoice;
    }

    /**
     * An invoice partly settled by cash and partly written off by credit — the
     * case that proves both ledgers feed one balance.
     */
    private function creditedInvoice(Company $company, Client $client): void
    {
        $invoice = $this->invoice($company, $client, 'INV-000003', [
            ['description' => 'Conference packages', 'unit' => 'pax', 'quantity' => 20, 'unit_price' => 450],
        ], 'sent', issuedDaysAgo: 25, dueInDays: 21);

        $this->pay($invoice, 5000, 'bank_transfer', daysAgo: 20, reference: 'FT2612·1109');

        $creditTotal = 4000.00;
        $creditSubtotal = round($creditTotal / 1.16, 2);

        $note = CreditNote::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'number' => 'CRN-000001',
            'reason' => 'Six delegates cancelled within the free window',
            'issue_date' => now()->subDays(14)->toDateString(),
            'currency_code' => $invoice->currency_code,
            'subtotal' => $creditSubtotal,
            'tax_percent' => 16,
            'tax_total' => round($creditTotal - $creditSubtotal, 2),
            'total' => $creditTotal,
            'status' => 'issued',
        ]);

        $note->items()->create([
            'description' => 'Conference packages — cancelled delegates',
            'unit' => 'pax',
            'quantity' => 6,
            'unit_price' => 450,
            'line_total' => 2700,
            'sort_order' => 0,
        ]);

        app(InvoiceSettlement::class)->sync($invoice->fresh());
    }

    private function overdueInvoice(Company $company, Client $client): void
    {
        $this->invoice($company, $client, 'INV-000004', [
            ['description' => 'Angle grinder discs', 'unit' => 'pack', 'quantity' => 10, 'unit_price' => 320],
        ], 'sent', issuedDaysAgo: 75, dueInDays: 30);
    }

    /**
     * Goods dispatched against an invoice, signed for on arrival.
     */
    private function deliveredInvoice(Company $company, Client $client): void
    {
        $invoice = $this->invoice($company, $client, 'INV-000005', [
            ['description' => 'Galvanised sheeting 0.5mm', 'unit' => 'sheet', 'quantity' => 40, 'unit_price' => 210],
            ['description' => 'Roofing nails', 'unit' => 'kg', 'quantity' => 15, 'unit_price' => 90],
        ], 'sent', issuedDaysAgo: 12, dueInDays: 30);

        $note = DeliveryNote::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'number' => 'DN-000001',
            'reference' => $invoice->number,
            'issue_date' => now()->subDays(11)->toDateString(),
            'delivery_date' => now()->subDays(10)->toDateString(),
            'currency_code' => $invoice->currency_code,
            'deliver_to' => $client->name,
            'delivery_address' => '88 Cha Cha Cha Road, Lusaka',
            'received_by' => 'J. Banda',
            'received_on' => now()->subDays(10)->toDateString(),
            'status' => 'delivered',
        ]);

        foreach ($invoice->items as $index => $item) {
            $note->items()->create([
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'sort_order' => $index,
            ]);
        }

        $invoice->update(['has_delivery_note' => true]);
    }

    private function quotationFor(Company $company, Client $client): void
    {
        $total = 18000.00;
        $subtotal = round($total / 1.16, 2);

        $quotation = Quotation::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'QUO-000001',
            'title' => 'Lodge re-roofing, phase one',
            'issue_date' => now()->subDays(6)->toDateString(),
            'valid_until' => now()->addDays(24)->toDateString(),
            'currency_code' => 'ZMW',
            'subtotal' => $subtotal,
            'tax_percent' => 16,
            'tax_total' => round($total - $subtotal, 2),
            'total' => $total,
            'status' => 'sent',
        ]);

        $quotation->items()->createMany([
            [
                'description' => 'Galvanised sheeting 0.5mm',
                'unit' => 'sheet',
                'quantity' => 60,
                'unit_price' => 210,
                'line_total' => 12600,
                'sort_order' => 0,
            ],
            [
                'description' => 'Labour, two-person crew',
                'unit' => 'day',
                'quantity' => 6,
                'unit_price' => 900,
                'line_total' => 5400,
                'sort_order' => 1,
            ],
        ]);
    }

    private function purchaseOrderFor(Company $company, Supplier $supplier): void
    {
        $total = 26000.00;
        $subtotal = round($total / 1.16, 2);

        $order = PurchaseOrder::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'number' => 'PO-000001',
            'title' => 'Q3 sheeting restock',
            'issue_date' => now()->subDays(4)->toDateString(),
            'expected_date' => now()->addDays(10)->toDateString(),
            'currency_code' => 'ZMW',
            'delivery_address' => '12 Cairo Road, Lusaka',
            'subtotal' => $subtotal,
            'tax_percent' => 16,
            'tax_total' => round($total - $subtotal, 2),
            'total' => $total,
            'status' => 'sent',
        ]);

        $order->items()->createMany([
            [
                'description' => 'Galvanised sheeting 0.5mm',
                'unit' => 'sheet',
                'quantity' => 100,
                'unit_price' => 185,
                'line_total' => 18500,
                'sort_order' => 0,
            ],
            [
                'description' => 'Roofing nails',
                'unit' => 'kg',
                'quantity' => 100,
                'unit_price' => 75,
                'line_total' => 7500,
                'sort_order' => 1,
            ],
        ]);
    }
}
