<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The frontend (votus-front) is served from a different origin than this
    | API, so browsers enforce CORS on every request. No config/cors.php
    | existed before this file, which meant Illuminate\Http\Middleware\HandleCors
    | never matched any path and added no CORS headers at all. This config
    | is intentionally permissive (wildcard origin, no credentials) so it
    | only ever widens access for the existing public, cookie-less endpoints
    | (deputies/senators/news) — it must stay credential-less unless a future
    | change deliberately narrows allowed_origins to specific domains first.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
