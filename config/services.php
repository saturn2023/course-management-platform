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

    /*
     * Staged rollout: with this on, only a logged-in admin reaches the v2
     * enrolment form. Everyone else falls through to the old flow, which stays
     * the live path for every course until this is switched off.
     *
     * Defaults to ON, unlike the reference, where AMS2_ADMIN_ONLY is false and
     * real students are already on v2. Nothing here is a security boundary: a
     * course is on v2 only once it has a published Enrolment Email template,
     * and this switch decides who sees that.
     */
    'admin_only' => env('AMS2_ADMIN_ONLY', true),

    /*
     * Ceiling for a single uploaded document, ported from AMS_DOC_MAX_BYTES.
     *
     * A ceiling, not the effective limit: Ams2DocumentLimit lowers it to
     * whatever upload_max_filesize and post_max_size actually allow, because a
     * file above those is discarded by PHP before any of this code runs.
     */
    'max_document_bytes' => env('AMS2_MAX_DOCUMENT_BYTES', 10 * 1024 * 1024),

    /*
     * The confirmation email's From address.
     *
     * Deliberately NOT MAIL_FROM_ADDRESS: that is whatever the environment
     * sends its own mail as, and on a dev machine it is usually the agency's
     * address. The reference hardcodes this one in the wp_mail header, so a
     * learner's confirmation has to come from it whatever the environment.
     */
    'mail_from' => env('AMS2_MAIL_FROM', 'info@amstraining.com.au'),
    'mail_from_name' => env('AMS2_MAIL_FROM_NAME', 'AMS Training'),
],

'pin' => [
    'publishable_key' => env('PIN_PUBLISHABLE_KEY'),
    'secret_key' => env('PIN_SECRET_KEY'),
    'base_url' => env('PIN_BASE_URL', 'https://test-api.pinpayments.com/1'),
    'sandbox' => env('PIN_SANDBOX', true),
],
];
