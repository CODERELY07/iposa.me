<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    | SMS Gateway for Android (sms-gate.app, open source). Your own Android phone sends the
    | texts. Cloud mode works from anywhere (default URL); local mode needs the phone to be
    | reachable from the server, e.g. http://192.168.1.20:8080/message.
    */
    'sms_gate' => [
        'url' => env('SMS_GATE_URL', 'https://api.sms-gate.app/3rdparty/v1/message'),
        'username' => env('SMS_GATE_USERNAME'),
        'password' => env('SMS_GATE_PASSWORD'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
