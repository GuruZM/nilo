<?php

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * @return array<string, mixed>
 */
function couponPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'LAUNCH20',
        'description' => 'Launch week promotion.',
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'currency_code' => null,
        'starts_at' => null,
        'expires_at' => null,
        'max_redemptions' => 100,
        'once_per_user' => true,
        'is_active' => true,
        'plan_ids' => [],
    ], $overrides);
}

function couponAdmin(): User
{
    test()->seed(RolePermissionSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('super-admin');

    return $user;
}

it('lists coupons with the plans they are restricted to', function () {
    $plan = Plan::factory()->create(['name' => 'Premium']);
    Coupon::factory()->create(['code' => 'ACTIVE1'])->plans()->attach($plan);
    Coupon::factory()->create(['code' => 'RETIRED', 'is_active' => false]);

    $this->actingAs(couponAdmin())
        ->get(route('admin.coupons.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/coupons/index')
            ->has('coupons', 2)
            ->where('coupons.0.code', 'ACTIVE1')
            ->where('coupons.0.plans.0.name', 'Premium')
        );
});

it('creates a coupon and restricts it to the chosen plans', function () {
    $premium = Plan::factory()->create();
    $standard = Plan::factory()->create();

    $this->actingAs(couponAdmin())
        ->post(route('admin.coupons.store'), couponPayload([
            'plan_ids' => [$premium->id, $standard->id],
        ]))
        ->assertRedirect(route('admin.coupons.index'));

    $coupon = Coupon::query()->where('code', 'LAUNCH20')->sole();

    expect($coupon->discount_type)->toBe('percentage')
        ->and((float) $coupon->discount_value)->toBe(20.0)
        ->and($coupon->max_redemptions)->toBe(100)
        ->and($coupon->redemptions_count)->toBe(0)
        ->and($coupon->plans->pluck('id')->sort()->values()->all())
        ->toBe(collect([$premium->id, $standard->id])->sort()->values()->all());
});

it('upper-cases a code typed in lower case', function () {
    $this->actingAs(couponAdmin())
        ->post(route('admin.coupons.store'), couponPayload(['code' => 'black-friday']))
        ->assertRedirect();

    $this->assertDatabaseHas('coupons', ['code' => 'BLACK-FRIDAY']);
});

it('drops the currency from a percentage coupon', function () {
    $this->actingAs(couponAdmin())
        ->post(route('admin.coupons.store'), couponPayload([
            'discount_type' => 'percentage',
            'currency_code' => 'ZMW',
        ]))
        ->assertRedirect();

    expect(Coupon::query()->sole()->currency_code)->toBeNull();
});

it('keeps the currency on a fixed coupon', function () {
    $this->actingAs(couponAdmin())
        ->post(route('admin.coupons.store'), couponPayload([
            'code' => 'FLAT50',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'currency_code' => 'ZMW',
        ]))
        ->assertRedirect();

    expect(Coupon::query()->sole()->currency_code)->toBe('ZMW');
});

it('rejects invalid input', function (array $overrides, string $field) {
    $this->actingAs(couponAdmin())
        ->post(route('admin.coupons.store'), couponPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing code' => [['code' => ''], 'code'],
    'code with spaces' => [['code' => 'SUMMER SALE'], 'code'],
    'unknown discount type' => [['discount_type' => 'freebie'], 'discount_type'],
    'zero discount' => [['discount_value' => 0], 'discount_value'],
    'percentage over 100' => [['discount_value' => 120], 'discount_value'],
    'fixed without a currency' => [
        ['discount_type' => 'fixed', 'discount_value' => 500, 'currency_code' => null],
        'currency_code',
    ],
    'expiry before start' => [
        ['starts_at' => '2026-08-01', 'expires_at' => '2026-07-01'],
        'expires_at',
    ],
    'zero redemption cap' => [['max_redemptions' => 0], 'max_redemptions'],
    'unknown plan' => [['plan_ids' => [9999]], 'plan_ids.0'],
]);

it('refuses a duplicate code regardless of casing', function () {
    Coupon::factory()->create(['code' => 'LAUNCH20']);

    $this->actingAs(couponAdmin())
        ->post(route('admin.coupons.store'), couponPayload(['code' => 'launch20']))
        ->assertSessionHasErrors('code');
});

it('updates a coupon and replaces its plan restrictions', function () {
    $old = Plan::factory()->create();
    $new = Plan::factory()->create();
    $coupon = Coupon::factory()->create(['code' => 'LAUNCH20']);
    $coupon->plans()->attach($old);

    $this->actingAs(couponAdmin())
        ->put(route('admin.coupons.update', $coupon), couponPayload([
            'discount_value' => 35,
            'is_active' => false,
            'plan_ids' => [$new->id],
        ]))
        ->assertRedirect(route('admin.coupons.index'));

    $coupon->refresh();

    expect((float) $coupon->discount_value)->toBe(35.0)
        ->and($coupon->is_active)->toBeFalse()
        ->and($coupon->plans->pluck('id')->all())->toBe([$new->id]);
});

it('lets a coupon keep its own code on update', function () {
    $coupon = Coupon::factory()->create(['code' => 'LAUNCH20']);

    $this->actingAs(couponAdmin())
        ->put(route('admin.coupons.update', $coupon), couponPayload())
        ->assertSessionHasNoErrors();
});

it('exposes the redemption count on the edit screen', function () {
    $coupon = Coupon::factory()->create();
    CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'user_id' => User::factory()->create()->id,
        'plan_id' => Plan::factory()->create()->id,
        'discount_amount' => 500,
        'currency_code' => 'ZMW',
    ]);

    $this->actingAs(couponAdmin())
        ->get(route('admin.coupons.edit', $coupon))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/coupons/edit')
            ->where('redemptions', 1)
        );
});

it('deletes a coupon nobody has redeemed', function () {
    $coupon = Coupon::factory()->create();

    $this->actingAs(couponAdmin())
        ->delete(route('admin.coupons.destroy', $coupon))
        ->assertRedirect(route('admin.coupons.index'));

    $this->assertDatabaseCount('coupons', 0);
});

it('keeps a redeemed coupon so its history survives', function () {
    $coupon = Coupon::factory()->create();
    CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'user_id' => User::factory()->create()->id,
        'plan_id' => Plan::factory()->create()->id,
        'discount_amount' => 500,
        'currency_code' => 'ZMW',
    ]);

    $this->actingAs(couponAdmin())
        ->delete(route('admin.coupons.destroy', $coupon))
        ->assertSessionHas('error');

    $this->assertDatabaseCount('coupons', 1);
});

it('offers only purchasable plans as restriction targets', function () {
    $listed = Plan::factory()->create();
    Plan::factory()->complimentary()->create();
    Plan::factory()->create(['is_active' => false]);

    $this->actingAs(couponAdmin())
        ->get(route('admin.coupons.create'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/coupons/create')
            ->has('plans', 1)
            ->where('plans.0.id', $listed->id)
        );
});

it('keeps the whole coupons area behind the admin role', function (string $method, string $uri) {
    $user = User::factory()->withSubscription()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->call($method, $uri)->assertForbidden();
})->with([
    'index' => ['get', '/admin/coupons'],
    'create' => ['get', '/admin/coupons/create'],
    'store' => ['post', '/admin/coupons'],
]);
