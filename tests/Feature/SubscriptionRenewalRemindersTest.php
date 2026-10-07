<?php

use App\Enums\SubscriptionReminder;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SubscriptionPaused;
use App\Notifications\SubscriptionPaymentDue;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

/**
 * A paid period that falls due at the given moment.
 *
 * @param  array<string, mixed>  $planOverrides
 */
function subscriptionDueAt(DateTimeInterface $endsAt, array $planOverrides = []): Subscription
{
    $plan = Plan::factory()->create([
        'price' => 100000,
        'currency_code' => 'ZMW',
        'billing_period' => 'monthly',
        ...$planOverrides,
    ]);

    return Subscription::factory()->create([
        'plan_id' => $plan->id,
        'ends_at' => $endsAt,
        'payment_method' => 'bank_transfer',
    ]);
}

function checkRenewals(): void
{
    test()->artisan('subscriptions:check-renewals')->assertSuccessful();
}

it('reminds a subscriber three days before payment is due, and only once', function () {
    $subscription = subscriptionDueAt(now()->addDays(3)->subHour());

    checkRenewals();
    checkRenewals();

    Notification::assertSentToTimes($subscription->user, SubscriptionPaymentDue::class, 1);
    Notification::assertSentTo(
        $subscription->user,
        SubscriptionPaymentDue::class,
        fn (SubscriptionPaymentDue $notification) => $notification->reminder === SubscriptionReminder::DueInThreeDays,
    );

    expect($subscription->fresh())
        ->reminder_stage->toBe(SubscriptionReminder::DueInThreeDays)
        ->reminder_sent_at->not->toBeNull();
});

it('says nothing while the due date is more than three days away', function () {
    subscriptionDueAt(now()->addDays(4));

    checkRenewals();

    Notification::assertNothingSent();
});

it('follows up with a final reminder the day before', function () {
    $subscription = subscriptionDueAt(now()->addDays(3)->subHour());

    checkRenewals();
    $this->travel(2)->days();
    checkRenewals();

    Notification::assertSentToTimes($subscription->user, SubscriptionPaymentDue::class, 2);
    expect($subscription->fresh()->reminder_stage)->toBe(SubscriptionReminder::DueTomorrow);
});

/**
 * A sweep that missed the three-day mark must not make up for it with two
 * emails in the same hour.
 */
it('sends only the latest reminder when the sweep comes late', function () {
    $subscription = subscriptionDueAt(now()->addHours(12));

    checkRenewals();

    Notification::assertSentToTimes($subscription->user, SubscriptionPaymentDue::class, 1);
    Notification::assertSentTo(
        $subscription->user,
        SubscriptionPaymentDue::class,
        fn (SubscriptionPaymentDue $notification) => $notification->reminder === SubscriptionReminder::DueTomorrow,
    );
});

it('warns an overdue subscriber who still has access during the grace period', function () {
    $subscription = subscriptionDueAt(now()->subHours(2));

    checkRenewals();

    Notification::assertSentTo(
        $subscription->user,
        SubscriptionPaymentDue::class,
        fn (SubscriptionPaymentDue $notification) => $notification->reminder === SubscriptionReminder::Overdue,
    );

    expect($subscription->fresh()->status)->toBe('active')
        ->and($subscription->user->fresh()->hasActiveSubscription())->toBeTrue();
});

it('pauses a subscription left unpaid for more than a day', function () {
    $subscription = subscriptionDueAt(now()->subHours(25));
    $user = $subscription->user;

    checkRenewals();

    expect($subscription->fresh())
        ->status->toBe('paused')
        ->paused_at->not->toBeNull();

    Notification::assertSentTo($user, SubscriptionPaused::class);
    Notification::assertNotSentTo($user, SubscriptionPaymentDue::class);

    expect($user->fresh()->hasActiveSubscription())->toBeFalse();

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('subscription.select'));
});

it('walks an unpaid period from first reminder to pause', function () {
    $subscription = subscriptionDueAt(now()->addDays(3)->subMinutes(30));

    checkRenewals();
    $this->travel(2)->days();
    checkRenewals();
    $this->travel(1)->days();
    checkRenewals();
    $this->travel(25)->hours();
    checkRenewals();

    Notification::assertSentToTimes($subscription->user, SubscriptionPaymentDue::class, 3);
    Notification::assertSentToTimes($subscription->user, SubscriptionPaused::class, 1);
    expect($subscription->fresh()->status)->toBe('paused');
});

/**
 * A customer whose transfer is sitting in the admin queue has done their part.
 * Their access still ends on time — an unverified reference must not hold the
 * app open — but they are neither nagged nor told they have been paused.
 */
it('holds off on a subscriber whose payment is waiting to be confirmed', function (int $hoursFromDue) {
    $subscription = subscriptionDueAt(now()->addHours($hoursFromDue));

    Payment::factory()->create([
        'user_id' => $subscription->user_id,
        'plan_id' => $subscription->plan_id,
        'status' => 'pending',
    ]);

    checkRenewals();

    Notification::assertNothingSent();
    expect($subscription->fresh()->status)->toBe('active');
})->with([
    'due in two days' => 48,
    'overdue within grace' => -2,
    'past the grace period' => -30,
]);

it('pauses once a pending payment is turned down', function () {
    $subscription = subscriptionDueAt(now()->subHours(30));

    $payment = Payment::factory()->create([
        'user_id' => $subscription->user_id,
        'plan_id' => $subscription->plan_id,
        'status' => 'pending',
    ]);

    checkRenewals();
    expect($subscription->fresh()->status)->toBe('active')
        ->and($subscription->user->fresh()->hasActiveSubscription())->toBeFalse();

    $payment->update(['status' => 'rejected']);
    checkRenewals();

    expect($subscription->fresh()->status)->toBe('paused');
    Notification::assertSentTo($subscription->user, SubscriptionPaused::class);
});

it('never chases a plan that does not fall due', function () {
    $subscription = Subscription::factory()->create(['ends_at' => null]);

    checkRenewals();

    Notification::assertNothingSent();
    expect($subscription->fresh()->status)->toBe('active');
});

it('pauses a lapsed free plan without asking anyone to pay for it', function () {
    $dueSoon = subscriptionDueAt(now()->addHours(12), ['price' => 0]);
    $lapsed = subscriptionDueAt(now()->subDays(2), ['price' => 0]);

    checkRenewals();

    Notification::assertNothingSent();
    expect($dueSoon->fresh()->status)->toBe('active')
        ->and($lapsed->fresh()->status)->toBe('paused');
});

it('pauses a long-lapsed subscription without announcing it', function () {
    $subscription = subscriptionDueAt(now()->subDays(30));

    checkRenewals();

    expect($subscription->fresh()->status)->toBe('paused');
    Notification::assertNothingSent();
});

it('leaves cancelled and already paused subscriptions alone', function () {
    $cancelled = Subscription::factory()->cancelled()->create(['ends_at' => now()->subDays(2)]);
    $paused = Subscription::factory()->paused()->create();
    $pausedAt = $paused->paused_at;

    checkRenewals();

    Notification::assertNothingSent();
    expect($cancelled->fresh()->status)->toBe('cancelled')
        ->and($paused->fresh()->paused_at->toIso8601String())->toBe($pausedAt->toIso8601String());
});

it('names the plan, price and due date in the reminder', function () {
    $subscription = subscriptionDueAt(Carbon::parse('2026-10-09 14:00'), ['name' => 'Standard']);

    $mail = (new SubscriptionPaymentDue($subscription, SubscriptionReminder::DueTomorrow))
        ->toMail($subscription->user);

    expect($mail->subject)->toBe('Final reminder: your Nilo payment is due on 9 October 2026')
        ->and((string) $mail->render())
        ->toContain('Standard plan (ZMW 100,000.00 per month)')
        ->toContain(route('subscription.select'));
});

it('tells an overdue subscriber how long they have before a pause', function () {
    $subscription = subscriptionDueAt(now()->subHours(2));

    $mail = (new SubscriptionPaymentDue($subscription, SubscriptionReminder::Overdue))
        ->toMail($subscription->user);

    expect($mail->subject)->toBe('Your Nilo payment is overdue')
        ->and((string) $mail->render())->toContain('within 24 hours of the due date');
});

it('tells a paused subscriber how to come back', function () {
    $subscription = Subscription::factory()->paused()->create();

    $mail = (new SubscriptionPaused($subscription))->toMail($subscription->user);

    expect($mail->subject)->toBe('Your Nilo subscription is paused')
        ->and((string) $mail->render())->toContain('Renew my plan');
});

it('is scheduled to run every hour', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'subscriptions:check-renewals'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *');
});
