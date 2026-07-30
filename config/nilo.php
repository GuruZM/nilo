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

];
