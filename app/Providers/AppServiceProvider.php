<?php

namespace App\Providers;

use App\Services\Dpo\DpoClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DpoClient::class, function () {
            $config = config('services.dpo');

            return new DpoClient(
                companyToken: (string) $config['company_token'],
                serviceType: (string) $config['service_type'],
                baseUrl: (string) $config['base_url'],
                paymentUrl: (string) $config['payment_url'],
                timeout: (int) $config['timeout'],
                connectTimeout: (int) $config['connect_timeout'],
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Document email goes out from the platform address, so a member cannot be
         * allowed to hammer the send buttons into a spam run that costs every
         * company its deliverability.
         */
        RateLimiter::for('document-email', function (Request $request) {
            return Limit::perMinute(10)->by('document-email:'.($request->user()?->id ?: $request->ip()));
        });
    }
}
