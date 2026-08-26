<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | allowed_origins SENGAJA tidak memakai wildcard '*' — API token
    | (DEC-02) dan sanctum/csrf-cookie tidak boleh diakses lintas-asal
    | dari domain sembarang. CORS_ALLOWED_ORIGINS berisi ORIGIN LENGKAP
    | (skema+host+port, mis. "http://localhost:8080"), BUKAN bare host
    | seperti SANCTUM_STATEFUL_DOMAINS — keduanya format berbeda,
    | jangan disatukan. Diisi domain SPA Vue yang sesungguhnya saat
    | dipasang (stateful, butuh supports_credentials true — kombinasi
    | itu TIDAK sah dengan '*' menurut spesifikasi CORS).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', env('APP_URL', 'http://localhost')))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
