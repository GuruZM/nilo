<?php

use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Both manual routes are now "send money to one of Nilo's accounts and tell us
 * the reference". Nothing asks the customer for their own number any more —
 * there was never anything on the other end of it to push a prompt to.
 */
it('records a mobile money payment against the reference the customer filed', function () {
    $user = dpoBuyer();
    $plan = dpoPlan();

    $this->actingAs($user)
        ->post('/subscription/payment', [
            'plan_id' => $plan->id,
            'payment_method' => 'mobile_money',
            'payment_reference' => 'MP250807.1423.A12345',
        ])
        ->assertRedirect('/subscription/payment-status');

    $payment = Payment::sole();

    expect($payment->payment_method)->toBe('mobile_money')
        ->and($payment->payment_reference)->toBe('MP250807.1423.A12345')
        ->and($payment->phone_number)->toBeNull()
        ->and($payment->status)->toBe('pending')
        ->and($payment->subscription->status)->toBe('pending_payment');
});

it('takes an optional receipt alongside a mobile money payment', function () {
    Storage::fake('public');
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())->post('/subscription/payment', [
        'plan_id' => $plan->id,
        'payment_method' => 'mobile_money',
        'payment_reference' => 'TX999',
        'pop_file' => UploadedFile::fake()->image('receipt.png'),
    ]);

    expect(Payment::sole()->pop_file_path)->not->toBeNull();
});

it('will not take a payment without a reference to match it by', function (string $method) {
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment', [
            'plan_id' => $plan->id,
            'payment_method' => $method,
        ])
        ->assertSessionHasErrors('payment_reference');

    expect(Payment::count())->toBe(0);
})->with(['mobile_money', 'bank_transfer']);

it('still demands proof of payment for a bank transfer', function () {
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment', [
            'plan_id' => $plan->id,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TX999',
        ])
        ->assertSessionHasErrors('pop_file');

    expect(Payment::count())->toBe(0);
});

it('no longer accepts the retired airtel money method', function () {
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment', [
            'plan_id' => $plan->id,
            'payment_method' => 'airtel_money',
            'payment_reference' => 'TX999',
        ])
        ->assertSessionHasErrors('payment_method');

    expect(Payment::count())->toBe(0);
});
