<?php

namespace App\Models;

use App\Notifications\WelcomeVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'linkedin_id',
        'current_company_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The social providers this account can sign in with.
     *
     * @return list<string>
     */
    public function linkedProviders(): array
    {
        return collect(['google', 'linkedin'])
            ->filter(fn (string $provider): bool => filled($this->providerId($provider)))
            ->values()
            ->all();
    }

    public function providerId(string $provider): ?string
    {
        return $this->{$provider.'_id'};
    }

    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['is_owner', 'status'])
            ->withTimestamps();
    }

    public function currentCompany()
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    public function isMemberOfCompany(int $companyId): bool
    {
        return $this->companies()->where('companies.id', $companyId)->exists();
    }

    /**
     * The currency this user wants figures reported in.
     *
     * The inverse of {@see Company::defaultCurrencyCodeFor()}: there the
     * company wins, because what a company bills in is not the viewer's
     * choice. Here the user's pick wins, because the currency switcher would
     * be inert otherwise.
     */
    public function displayCurrencyCode(): string
    {
        return strtoupper((string) (
            $this->current_currency_code
            ?: Company::defaultCurrencyCodeFor($this->current_company_id)
        ));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    /**
     * The companies this user belongs to.
     */

    /**
     * Companies owned by this user.
     */
    public function ownedCompanies()
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    /**
     * CompanyUser pivot records for this user.
     */
    public function companyUsers()
    {
        return $this->hasMany(CompanyUser::class);
    }

    /**
     * Every subscription row this user has ever had.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The newest subscription row, whatever its status.
     *
     * This is the history view — during a checkout it is the pending row, not
     * the plan the user is entitled to. Reach for activeSubscription() for
     * anything that decides what the user may do.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * The subscription that grants access.
     *
     * Constrained by status rather than by subscription()'s newest-row rule
     * because checkout files a second, pending row: without this an upgrade in
     * flight reads as "no plan" and the subscribed middleware turns a paying
     * customer out of the app halfway through paying. The ends_at test stays in
     * Subscription::isActive() — see activePlan().
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->where('status', 'active'),
        );
    }

    /**
     * The upgrade waiting on a payment, if any.
     */
    public function pendingSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->where('status', 'pending_payment'),
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activePlan(): ?Plan
    {
        $subscription = $this->activeSubscription;

        if ($subscription && $subscription->isActive()) {
            return $subscription->plan;
        }

        return null;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activePlan() !== null;
    }

    /**
     * Enrol the user on the free tier so they can reach the dashboard
     * without being forced to pick a plan first. Returns null when no
     * active free plan exists, letting callers fall back to selection.
     *
     * Guards on subscription() rather than activeSubscription() deliberately:
     * every caller runs immediately after registration, so "this account has
     * any subscription row at all" is the conservative test. Checking only for
     * an active one would hand a free plan to someone whose plan was cancelled.
     */
    public function subscribeToFreePlan(): ?Subscription
    {
        if ($this->subscription) {
            return $this->subscription;
        }

        $freePlan = Plan::query()
            ->where('slug', 'free')
            ->where('is_active', true)
            ->first();

        if (! $freePlan) {
            return null;
        }

        $subscription = Subscription::create([
            'user_id' => $this->id,
            'plan_id' => $freePlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
            'payment_method' => 'free',
        ]);

        $this->setRelation('subscription', $subscription);

        return $subscription;
    }

    /**
     * Send the branded welcome and email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new WelcomeVerifyEmail);
    }
}
