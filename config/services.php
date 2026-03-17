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

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'), // log, africastalking, hubtel
    ],

    'africastalking' => [
        'username' => env('AT_USERNAME'),
        'api_key' => env('AT_API_KEY'),
        'sender_id' => env('AT_SENDER_ID', 'CediBites'),
    ],

    'hubtel' => [
        // SMS credentials
        'client_id' => env('HUBTEL_CLIENT_ID'),
        'client_secret' => env('HUBTEL_CLIENT_SECRET'),
        'sender_id' => env('HUBTEL_SENDER_ID', 'CediBites'),
        'sms_base_url' => env('HUBTEL_SMS_BASE_URL', 'https://sms.hubtel.com/v1/messages'),

        // Payment gateway credentials (can be same as SMS or different)
        'payment_client_id' => env('HUBTEL_PAYMENT_CLIENT_ID', env('HUBTEL_CLIENT_ID')),
        'payment_client_secret' => env('HUBTEL_PAYMENT_CLIENT_SECRET', env('HUBTEL_CLIENT_SECRET')),
        'merchant_account_number' => env('HUBTEL_MERCHANT_ACCOUNT_NUMBER'),
        'base_url' => env('HUBTEL_BASE_URL', 'https://payproxyapi.hubtel.com'),
        'status_check_url' => env('HUBTEL_STATUS_CHECK_URL', 'https://api-txnstatus.hubtel.com'),

        /*
         * IMPORTANT: The Hubtel Status Check API requires IP whitelisting.
         * You must contact Hubtel support to whitelist your server's IP address
         * before the verifyTransaction() method will work in production.
         * Without IP whitelisting, status check requests will be rejected.
         */
    ],

];
