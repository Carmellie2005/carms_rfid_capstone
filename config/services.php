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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'webpush' => [
        'vapid_subject' => env('WEBPUSH_VAPID_SUBJECT', env('MAIL_FROM_ADDRESS') ? 'mailto:'.env('MAIL_FROM_ADDRESS') : env('APP_URL')),
        'vapid_public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
        'vapid_private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),
        'ttl' => env('WEBPUSH_TTL', 3600),
    ],

];
