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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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
        'mode' => env('STRIPE_MODE', 'test'),
        'test' => [
            'key' => env('STRIPE_TEST_PUBLIC_KEY'),
            'secret' => env('STRIPE_TEST_SECRET_KEY'),
        ],
        'live' => [
            'key' => env('STRIPE_LIVE_PUBLIC_KEY'),
            'secret' => env('STRIPE_LIVE_SECRET_KEY'),
        ],
        'key' => env('STRIPE_MODE', 'test') === 'live' ? env('STRIPE_LIVE_PUBLIC_KEY') : env('STRIPE_TEST_PUBLIC_KEY'),
        'secret_key' => env('STRIPE_MODE', 'test') === 'live' ? env('STRIPE_LIVE_SECRET_KEY') : env('STRIPE_TEST_SECRET_KEY'),
        'stripe_webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // HireVire — Stage 2 video interview platform
    'hirevire' => [
        'webhook_secret' => env('HIREVIRE_WEBHOOK_SECRET', null),
        'interview_url'  => env('HIREVIRE_INTERVIEW_URL', 'https://app.hirevire.com/applications/091820fa-6fef-45e0-97e8-d714fc0b27cf'),
    ],

    // Trafft — Stage 3 face-to-face interview booking (Zoom)
    'trafft' => [
        'webhook_secret'     => env('TRAFFT_WEBHOOK_SECRET', null),
        'booking_url'        => env('TRAFFT_BOOKING_URL', 'https://vanquishtherapies.co.uk/placement-interview/'),
        'default_zoom_link'  => env('TRAFFT_DEFAULT_ZOOM_LINK', 'https://zoom.us/j/vanquishtherapies'),
        // Update this whenever the next induction cohort date changes:
        'next_induction_date' => env('TRAFFT_NEXT_INDUCTION_DATE', 'Monday, 19th January, 10:00am'),
    ],

    // Zoom Meeting SDK — Embedded meetings
    'zoom' => [
        'sdk_key'    => env('ZOOM_SDK_KEY'),
        'sdk_secret' => env('ZOOM_SDK_SECRET'),
    ],

];
