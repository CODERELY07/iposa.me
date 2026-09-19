<?php

/*
|--------------------------------------------------------------------------
| iPOSa deployment settings
|--------------------------------------------------------------------------
| Read by `php artisan app:ensure-super-admin` (run on every deploy) and the
| container start script. Set these as environment variables on the host.
*/

return [

    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL'),
        'name' => env('SUPER_ADMIN_NAME', 'Platform Operator'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

];
