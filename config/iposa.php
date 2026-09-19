<?php

/*
|--------------------------------------------------------------------------
| iPOSa deployment settings
|--------------------------------------------------------------------------
| Read by `php artisan app:ensure-super-admin` (run on every deploy), the
| container start script and the "contact an agent" screens.
| Set these as environment variables on the host.
*/

return [

    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL'),
        'name' => env('SUPER_ADMIN_NAME', 'Platform Operator'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    // Shown when an email can't be sent, so owners can reach an iPOSa agent instead.
    'support' => [
        'email' => env('SUPPORT_EMAIL', env('SUPER_ADMIN_EMAIL')),
        'phone' => env('SUPPORT_PHONE'),
        'messenger_url' => env('SUPPORT_MESSENGER_URL'),
    ],

];
