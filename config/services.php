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

/*
 * Enrolment v2.
 *
 * Credentials, base URL, subdomain and origin are shared with 'enrolment_api'
 * above — same RTO Data account, same /api/v2 endpoint. There is deliberately
 * no second copy of the key to drift out of step.
 *
 * Timeouts are NOT shared. The old flow's 20s would send slow-but-successful
 * responses to the verify path far more often than WordPress does; these are
 * the values ported from ams2_api().
 *
 * 'enabled' is the kill switch: with it off, ams2:process exits before doing
 * anything, so no row can reach RTO Data.
 *
 * 'enrolment_floor' is a safety floor for the verify step. This database does
 * not know about enrolments WordPress has already created in RTO Data, so a
 * floor computed only from local rows could link an OLD WordPress enrolment for
 * the same learner to a new row. With no floor set, verify refuses to run.
 */
'ams2' => [
    'enabled' => env('AMS2_ENABLED', false),
    'connect_timeout' => env('AMS2_CONNECT_TIMEOUT', 15),
    'timeout' => env('AMS2_TIMEOUT', 90),
    'enrolment_floor' => env('AMS2_ENROLMENT_FLOOR'),
],

'pin' => [
    'publishable_key' => env('PIN_PUBLISHABLE_KEY'),
    'secret_key' => env('PIN_SECRET_KEY'),
    'base_url' => env('PIN_BASE_URL', 'https://test-api.pinpayments.com/1'),
    'sandbox' => env('PIN_SANDBOX', true),
],
];
