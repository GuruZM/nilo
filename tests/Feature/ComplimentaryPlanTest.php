<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;

it('keeps a complimentary plan off the welcome page', function () {
    Plan::factory()->create(['slug' => 'standard']);
    Plan::factory()->complimentary()->create(['slug' => 'resonantt']);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('plans', fn ($plans) => collect($plans)->contains('slug', 'standard')
                && ! collect($plans)->contains('slug', 'resonantt')
            )
        );
});

it('keeps a complimentary plan off the plan selection screen', function () {
    Plan::factory()->create(['slug' => 'standard']);
    Plan::factory()->complimentary()->create(['slug' => 'resonantt']);

    $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->get(route('subscription.select'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('plans', fn ($plans) => ! collect($plans)->contains('slug', 'resonantt'))
        );
});

it('keeps a complimentary plan off the current subscription upgrade list', function () {
    $resonantt = Plan::factory()->complimentary()->create(['slug' => 'resonantt']);
    $user = User::factory()->create(['email_verified_at' => now()]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $resonantt->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('subscription.current'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('plans', fn ($plans) => ! collect($plans)->contains('slug', 'resonantt'))
            ->where('subscription.plan.slug', 'resonantt')
        );
});

it('refuses a self-service subscription to a complimentary plan', function () {
    $resonantt = Plan::factory()->complimentary()->create(['slug' => 'resonantt']);

    $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->post(route('subscription.subscribe'), ['plan_id' => $resonantt->id])
        ->assertForbidden();

    expect(Subscription::where('plan_id', $resonantt->id)->count())->toBe(0);
});

it('refuses to open the payment page for a complimentary plan', function () {
    $resonantt = Plan::factory()->complimentary()->create(['slug' => 'resonantt']);

    $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->get(route('subscription.payment', $resonantt))
        ->assertForbidden();
});

it('refuses to record a payment against a complimentary plan', function () {
    $resonantt = Plan::factory()->complimentary()->create(['slug' => 'resonantt']);

    $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->post(route('subscription.payment.store'), [
            'plan_id' => $resonantt->id,
            'payment_method' => 'airtel_money',
            'phone_number' => '0977123456',
        ])
        ->assertForbidden();

    expect(Subscription::where('plan_id', $resonantt->id)->count())->toBe(0);
});

it('seeds the Resonantt plan as unlimited, free and unlisted', function () {
    $this->seed(PlanSeeder::class);

    $resonantt = Plan::where('slug', 'resonantt')->first();

    expect($resonantt)->not->toBeNull()
        ->and($resonantt->is_active)->toBeTrue()
        ->and($resonantt->is_public)->toBeFalse()
        ->and($resonantt->is_popular)->toBeFalse()
        ->and((float) $resonantt->price)->toBe(0.0)
        ->and($resonantt->max_companies)->toBe(-1)
        ->and($resonantt->max_invoices)->toBe(-1)
        ->and($resonantt->max_quotations)->toBe(-1)
        ->and($resonantt->can_upload_custom_template)->toBeTrue();
});

it('leaves the sellable plans listed after seeding', function () {
    $this->seed(PlanSeeder::class);

    expect(Plan::query()->publiclyAvailable()->pluck('slug')->all())
        ->toBe(['free', 'standard', 'premium', 'enterprise']);
});
