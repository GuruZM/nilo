<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A user with an active company, an invoice template and one client, which is
 * the minimum state the invoice create endpoint accepts.
 *
 * @return array{0: \App\Models\User, 1: \App\Models\Client, 2: \App\Models\InvoiceTemplate}
 */
function invoiceCreationContext(?string $clientEmail): array
{
    $user = App\Models\User::factory()->withSubscription()->create();
    $company = App\Models\Company::factory()->create(['currency_code' => 'ZMW']);

    $user->companies()->attach($company->id, [
        'is_owner' => true,
        'status' => 'active',
    ]);
    $user->forceFill(['current_company_id' => $company->id])->save();

    $client = App\Models\Client::factory()->create([
        'company_id' => $company->id,
        'email' => $clientEmail,
    ]);

    $template = App\Models\InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Default',
        'type' => 'invoice',
        'is_default' => true,
        'settings' => [],
    ]);

    return [$user, $client, $template];
}

/**
 * The quotation equivalent of {@see invoiceCreationContext()} — the template is
 * scoped to the quotation type, which is what the create endpoint requires.
 *
 * @return array{0: \App\Models\User, 1: \App\Models\Client, 2: \App\Models\InvoiceTemplate}
 */
function quotationCreationContext(?string $clientEmail): array
{
    [$user, $client] = invoiceCreationContext($clientEmail);

    $template = App\Models\InvoiceTemplate::create([
        'company_id' => $user->current_company_id,
        'name' => 'Default quotation',
        'type' => 'quotation',
        'is_default' => true,
        'settings' => [],
    ]);

    return [$user, $client, $template];
}

/**
 * @return array<string, mixed>
 */
function quotationPayload(App\Models\Client $client, App\Models\InvoiceTemplate $template, bool $sendToClient): array
{
    return [
        'client_id' => $client->id,
        'quotation_template_id' => $template->id,
        'issue_date' => '2026-07-01',
        'valid_until' => '2026-07-31',
        'currency_code' => 'ZMW',
        'status' => 'draft',
        'quotation_discount' => 0,
        'tax_percent' => 0,
        'send_to_client' => $sendToClient,
        'items' => [
            [
                'description' => 'Consulting',
                'quantity' => 2,
                'unit_price' => 500,
                'discount' => 0,
            ],
        ],
    ];
}

/**
 * An invoice worth `$total` in the acting user's active company, ready to be
 * paid against.
 *
 * @return array{0: \App\Models\User, 1: \App\Models\Invoice}
 */
function payableInvoiceContext(float $total = 5000.0): array
{
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $invoice = App\Models\Invoice::query()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'invoice_template_id' => $template->id,
        'number' => 'INV-000001',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency_code' => 'ZMW',
        'subtotal' => $total,
        'total' => $total,
        'status' => 'sent',
    ]);

    return [$user, $invoice];
}

/**
 * @return array<string, mixed>
 */
function invoicePayload(App\Models\Client $client, App\Models\InvoiceTemplate $template, bool $sendToClient): array
{
    return [
        'client_id' => $client->id,
        'invoice_template_id' => $template->id,
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency_code' => 'ZMW',
        'status' => 'pending',
        'has_delivery_note' => false,
        'is_recurring' => false,
        'invoice_discount' => 0,
        'tax_percent' => 0,
        'send_to_client' => $sendToClient,
        'items' => [
            [
                'description' => 'Consulting',
                'quantity' => 2,
                'unit_price' => 500,
                'discount' => 0,
            ],
        ],
    ];
}
