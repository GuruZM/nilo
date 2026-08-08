<?php

namespace App\Providers;

use App\Services\Dpo\DpoClient;
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
        //
    }
}
