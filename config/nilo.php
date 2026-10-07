<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | Credentials used by the SuperAdminSeeder to provision the internal Nilo
    | staff account that can reach the /admin area without a subscription.
    |
    */

    'super_admin' => [
        'name' => env('NILO_SUPER_ADMIN_NAME', 'Nilo Admin'),
        'email' => env('NILO_SUPER_ADMIN_EMAIL', 'admin@nilo.test'),
        'password' => env('NILO_SUPER_ADMIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | Nothing renews itself, so a subscription falls due at its ends_at. The
    | grace period is how long access survives past that date before the
    | subscriptions:check-renewals sweep pauses the subscription.
    |
    */

    'billing' => [
        'grace_hours' => 24,
    ],

];
