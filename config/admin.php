<?php

return [

    /*
    |--------------------------------------------------------------------
    | Admin Panel URL Prefix
    |--------------------------------------------------------------------
    |
    | The admin panel is intentionally served under a non-obvious path
    | instead of the guessable "/admin". Rotate this to a private value
    | per environment via ADMIN_PANEL_PREFIX in .env — never commit a
    | real production value here.
    |
    */

    'prefix' => env('ADMIN_PANEL_PREFIX', 'panel-94267def'),

    /*
    |--------------------------------------------------------------------
    | Initial Superadmin
    |--------------------------------------------------------------------
    |
    | Account created by `php artisan db:seed` when no user with this
    | email exists yet. Required in production; the password must pass
    | Password::defaults(). Never commit real credentials here.
    |
    */

    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Superadmin'),
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

];
