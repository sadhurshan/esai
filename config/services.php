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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'checkout_success_url' => env('STRIPE_CHECKOUT_SUCCESS_URL'),
        'checkout_cancel_url' => env('STRIPE_CHECKOUT_CANCEL_URL'),
        'fallback_checkout_url' => env('STRIPE_CHECKOUT_FALLBACK_URL'),
        'portal_return_url' => env('STRIPE_PORTAL_RETURN_URL'),
        'portal_fallback_url' => env('STRIPE_PORTAL_FALLBACK_URL'),
        'portal_configuration' => env('STRIPE_PORTAL_CONFIGURATION'),
        'prices' => [
            'community' => env('STRIPE_PRICE_COMMUNITY'),
            'starter' => env('STRIPE_PRICE_STARTER'),
            'growth' => env('STRIPE_PRICE_GROWTH'),
            'enterprise' => env('STRIPE_PRICE_ENTERPRISE'),
        ],
        'past_due_grace_days' => (int) env('STRIPE_PAST_DUE_GRACE_DAYS', 7),
        'stub_trial_days' => (int) env('STRIPE_STUB_TRIAL_DAYS', 90),
        'invoice_history_limit' => (int) env('STRIPE_INVOICE_HISTORY_LIMIT', 12),
    ],

    'enterprise' => [
        'contact_url' => env('ENTERPRISE_CONTACT_URL'),
    ],

    'ai_microservice' => [
        'base_url' => env('AI_MICROSERVICE_URL'),
        'timeout' => (int) env('AI_MICROSERVICE_TIMEOUT', 15),
    ],

];
