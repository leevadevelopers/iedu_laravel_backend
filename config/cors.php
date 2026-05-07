<?php

$allowedOrigins = array_filter(array_map(
    static fn ($origin) => trim($origin),
    explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:4200,http://127.0.0.1:4200,https://iedu.digital,https://www.iedu.digital,https://app.iedu.digital'
    ))
));

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Adjust these options as needed when adding new frontend domains.
    |
    */
    // Include both api/* and public/api/* because production can expose Laravel
    // under /public and browsers still perform preflight against that URL.
    'paths' => ['api/*', 'public/api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    // Fallback pattern for trusted iedu subdomains.
    'allowed_origins_patterns' => ['#^https://([a-z0-9-]+\.)?iedu\.digital$#'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
