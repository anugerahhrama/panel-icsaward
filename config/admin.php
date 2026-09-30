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

];
