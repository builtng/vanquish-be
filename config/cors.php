<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],


    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'https://vanquish0.vercel.app,https://vanquish-5jaa9aeql-gen6ixxs-projects.vercel.app,http://localhost:3000,http://127.0.0.1:3000,https://vqtmanagement.com,https://www.vqtmanagement.com,https://api.vqtmanagement.com,http://localhost:3001,http://127.0.0.1:3001')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
