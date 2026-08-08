<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Daily FX rates. A free key removes the attribution requirement that the
     * keyless open endpoint carries; without one we fall back to that endpoint.
     */
    'exchangerate' => [
        'key' => env('EXCHANGERATE_API_KEY'),
        'keyed_url' => env('EXCHANGERATE_KEYED_URL', 'https://v6.exchangerate-api.com/v6'),
        'open_url' => env('EXCHANGERATE_OPEN_URL', 'https://open.er-api.com/v6/latest'),
        'timeout' => env('EXCHANGERATE_TIMEOUT', 15),
    ],

    /*
     * `client_ids` is the audience allow-list for the mobile ID-token exchange,
     * not a second set of credentials. Native Google Sign-In mints a token
     * addressed to the platform's own client id, so the web one alone would
     * reject every phone. Verification fails closed: an empty list matches
     * nothing.
     */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'client_ids' => array_values(array_filter([
            env('GOOGLE_CLIENT_ID'),
            env('GOOGLE_CLIENT_ID_ANDROID'),
            env('GOOGLE_CLIENT_ID_IOS'),
        ])),
        'tokeninfo_url' => env('GOOGLE_TOKENINFO_URL', 'https://oauth2.googleapis.com/tokeninfo'),
    ],

    'linkedin-openid' => [
        'enabled' => env('LINKEDIN_ENABLED', false),
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_REDIRECT_URI'),
    ],

    /*
     * DirectPay Online (DPO Group), the card and mobile-money gateway used for
     * subscription checkout. `service_type` is the merchant's service ID from
     * the DPO back office, not a category — a wrong one is rejected at
     * createToken with no useful message.
     *
     * `callback_base` exists apart from app.url because DPO's WAF rejects any
     * request whose body carries a localhost URL, so testing locally needs a
     * tunnel host without moving APP_URL and breaking every mail link.
     *
     * `fx_markup` is a percentage added to a USD charge. Our rate is the
     * mid-market one; DPO settles at its own and keeps the spread, so this is
     * the knob that covers it without a schema change. Zero until priced.
     */
    'dpo' => [
        'enabled' => env('DPO_ENABLED', false),
        'company_token' => env('DPO_COMPANY_TOKEN'),
        'service_type' => env('DPO_SERVICE_TYPE'),
        'base_url' => env('DPO_BASE_URL', 'https://secure.3gdirectpay.com/API/v6/'),
        'payment_url' => env('DPO_PAYMENT_URL', 'https://secure.3gdirectpay.com/payv2.php'),
        'callback_base' => env('DPO_CALLBACK_BASE'),
        'default_currency' => env('DPO_DEFAULT_CURRENCY', 'ZMW'),
        // Sent as the customer's own country. Stored against the transaction;
        // it does not by itself change what the hosted page shows.
        'customer_country' => env('DPO_CUSTOMER_COUNTRY', 'ZM'),
        // Which payment option the hosted page opens on: CC, MO, PP, BT or XP.
        'default_payment' => env('DPO_DEFAULT_PAYMENT', 'MO'),
        // Pre-selects the country on the mobile money option, which is the one
        // the customer would otherwise have to pick themselves. DPO wants the
        // full country name here, not the ISO code, and ignores it unless the
        // default payment option is MO.
        'default_payment_country' => env('DPO_DEFAULT_PAYMENT_COUNTRY', 'Zambia'),
        'allowed_currencies' => ['ZMW', 'USD'],
        'fx_markup' => env('DPO_FX_MARKUP', 0),
        'ptl_hours' => env('DPO_PTL_HOURS', 24),
        'timeout' => env('DPO_TIMEOUT', 30),
        'connect_timeout' => env('DPO_CONNECT_TIMEOUT', 10),
    ],

];
