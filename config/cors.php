<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    // Bearer-token clients do not require credentialed cross-origin cookies.
    // Operators can restrict origins without changing the authentication model.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*'))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Content-Disposition', 'Retry-After'],
    'max_age' => 600,
    'supports_credentials' => false,
];
