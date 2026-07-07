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
'xero' => [
    'client_id' => env('XERO_CLIENT_ID'),
    'client_secret' => env('XERO_CLIENT_SECRET'),
    'redirect_uri' => env('XERO_REDIRECT_URI'),

    /*
     |--------------------------------------------------------------------------
     | Invoice lifecycle flags
     |--------------------------------------------------------------------------
     |
     | Current testing phase: invoices are created as DRAFT and automatic
     | billing-email delivery is OFF, so a draft invoice is never sent to a
     | customer. Admins review the invoice directly in Xero.
     |
     | To go to production, set in .env:
     |   XERO_INVOICE_STATUS=AUTHORISED      (finalise as official tax invoice)
     |   XERO_AUTO_EMAIL_INVOICE=true        (auto-email the official PDF)
     |
     | These are the only two switches needed to enable automatic billing-email
     | delivery — the XeroService and SendXeroInvoiceEmailJob are already wired.
     */
    'invoice_status' => env('XERO_INVOICE_STATUS', 'DRAFT'),
    'auto_email_invoice' => env('XERO_AUTO_EMAIL_INVOICE', false),
],
'enrolment_api' => [
    'enabled' => env('ENROLMENT_API_ENABLED', false),
    'base_url' => env('ENROLMENT_API_BASE_URL'),
    'public_key' => env('ENROLMENT_API_PUBLIC_KEY'),
    'subdomain' => env('ENROLMENT_API_SUBDOMAIN', 'amstraining'),
    'origin' => env('ENROLMENT_API_ORIGIN', config('app.url')),
    'timeout' => env('ENROLMENT_API_TIMEOUT', 30),
    'connect_timeout' => env('ENROLMENT_API_CONNECT_TIMEOUT', 10),
],

'pin' => [
    'publishable_key' => env('PIN_PUBLISHABLE_KEY'),
    'secret_key' => env('PIN_SECRET_KEY'),
    'base_url' => env('PIN_BASE_URL', 'https://test-api.pinpayments.com/1'),
    'sandbox' => env('PIN_SANDBOX', true),
],
];
