<?php

use App\Models\Currency;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Currency::create([
        'code' => 'ZMW',
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);

    Mail::fake();
});

it('tells the invoice page it was just created so the confetti fires', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, false))
        ->assertRedirect();

    $invoice = Invoice::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}")
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Invoices/show')
                ->where('justCreated', true)
        );
});

it('does not celebrate an invoice opened later', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/invoices', invoicePayload($client, $template, false));
    $invoice = Invoice::query()->latest('id')->first();

    /** Consume the creation flash, then revisit as a normal page view. */
    $this->actingAs($user)->get("/invoices/{$invoice->id}");

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}")
        ->assertInertia(
            fn (Assert $page) => $page->where('justCreated', false)
        );
});

it('celebrates without pulling in a confetti dependency', function () {
    $packageJson = json_decode(
        file_get_contents(__DIR__.'/../../package.json'),
        true
    );

    $installed = array_keys(array_merge(
        $packageJson['dependencies'] ?? [],
        $packageJson['devDependencies'] ?? []
    ));

    expect($installed)->not->toContain('canvas-confetti')
        ->and($installed)->toContain('framer-motion');

    expect(file_get_contents(__DIR__.'/../../resources/js/components/confetti-burst.tsx'))
        ->toContain('useReducedMotion')
        ->toContain('pointer-events-none');
});

it('gives the slow actions the brand loader rather than a bare label', function () {
    $create = file_get_contents(__DIR__.'/../../resources/js/pages/Invoices/Create.tsx');
    $dialog = file_get_contents(__DIR__.'/../../resources/js/components/edit-client-dialog.tsx');

    expect($create)
        ->toContain("import NiloSpinner from '@/components/nilo-spinner'")
        // The two wizard actions that actually wait on the server.
        ->toContain('Creating…')
        ->toContain('Building preview…')
        // The preview fetch previously gave no feedback at all.
        ->toContain('setPreviewLoading(true);')
        ->toContain('setPreviewLoading(false);');

    // The third is the client dialog, now shared with the quotation builder.
    expect($dialog)
        ->toContain("import NiloSpinner from '@/components/nilo-spinner'")
        ->toContain('Saving…');
});

/** The quotation builder is the same wizard, so it waits out loud too. */
it('gives the quotation wizard the same loading feedback', function () {
    $create = file_get_contents(__DIR__.'/../../resources/js/pages/Quotations/Create.tsx');

    expect($create)
        ->toContain("import NiloSpinner from '@/components/nilo-spinner'")
        ->toContain('Creating…')
        ->toContain('Building preview…')
        ->toContain('setPreviewLoading(true);')
        ->toContain('setPreviewLoading(false);');
});

/**
 * The subscription limit refuses with a flash rather than validation errors.
 * Nothing surfaced it, so the user was bounced back to step one of the wizard
 * with no message and no invoice — which read as the form silently resetting.
 */
it('refuses over the invoice limit with a notice rather than silence', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    /** Allow exactly one invoice, then use it up. */
    $user->activePlan()->update(['max_invoices' => 1]);
    $this->actingAs($user)->post('/invoices', invoicePayload($client, $template, false));

    expect(Invoice::query()->count())->toBe(1);

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, false))
        ->assertSessionHas('limit_notice')
        ->assertSessionMissing('invoice_created');

    /** The second attempt created nothing. */
    expect(Invoice::query()->count())->toBe(1);
});

it('hands the create page a notice it can render as a dialog', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $user->activePlan()->update(['max_invoices' => 1]);
    $this->actingAs($user)->post('/invoices', invoicePayload($client, $template, false));
    $this->actingAs($user)->post('/invoices', invoicePayload($client, $template, false));

    $this->actingAs($user)
        ->get('/invoices/create')
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('limitNotice.title', 'Plan limit reached')
                ->where('limitNotice.action_href', route('subscription.select'))
                ->where(
                    'limitNotice.message',
                    fn (string $message) => str_contains(
                        $message,
                        'does not cover any more invoices'
                    )
                )
        );
});

/**
 * The old wording claimed a limit was reached even for accounts that never had
 * a plan. Asserted on the service directly because the create routes sit behind
 * the subscription middleware, so that state never reaches the controller.
 */
it('says there is no plan rather than blaming a limit', function () {
    [$user] = invoiceCreationContext('client@example.com');

    $user->subscription?->delete();
    $user->refresh();

    $notice = (new App\Services\SubscriptionLimitService($user))
        ->limitNotice('invoices');

    expect($notice['title'])->toBe('No active plan')
        ->and($notice['action_label'])->toBe('Choose a plan')
        ->and($notice['message'])->toContain('no active plan');
});

it('shows refusals as a dialog and keeps the wizard in place', function () {
    $create = file_get_contents(__DIR__.'/../../resources/js/pages/Invoices/Create.tsx');

    expect($create)
        // A plan refusal stops the work, so it gets a dialog rather than a toast.
        ->toContain('<LimitNoticeDialog notice={limitNotice} />')
        // Other flashed errors are still surfaced.
        ->toContain('if (flash?.error) toast.error(flash.error);')
        // Without this a refusal remounted the wizard back to step one.
        ->toContain('preserveState: true,');

    expect(file_get_contents(__DIR__.'/../../resources/js/components/limit-notice-dialog.tsx'))
        ->toContain('<Info className="h-6 w-6" />');
});
