<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bearer-token (API) JWT settings
    |--------------------------------------------------------------------------
    |
    | Read through the config layer, never with env() at the point of use.
    | Once `php artisan config:cache` has run — which the deploy runbook makes
    | mandatory on every deploy — the .env file is not read again, and an
    | env() call returns null. A JwtService that read env('JWT_SECRET')
    | directly therefore fell back to its hard-coded default in production and
    | signed every API token with a publicly known secret. Capturing the value
    | here means `config:cache` bakes the real secret in.
    |
    */

    'secret' => env('JWT_SECRET'),

    'ttl' => (int) env('JWT_TTL_SECS', 86400),

];
