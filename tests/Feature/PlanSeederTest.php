<?php

use App\Models\Plan;
use Database\Seeders\PlanSeeder;

it('seeds plans with features as an array, not a double-encoded string', function () {
    $this->seed(PlanSeeder::class);

    $plans = Plan::all();

    expect($plans)->not->toBeEmpty();

    foreach ($plans as $plan) {
        expect($plan->features)->toBeArray(
            "Plan [{$plan->slug}] features should cast to an array"
        );
    }

    expect(Plan::where('slug', 'free')->first()->features)
        ->toContain('1 Company');
});

it('is idempotent and does not corrupt features on re-seed', function () {
    $this->seed(PlanSeeder::class);
    $this->seed(PlanSeeder::class);

    expect(Plan::where('slug', 'free')->count())->toBe(1);
    expect(Plan::where('slug', 'free')->first()->features)->toBeArray();
});
