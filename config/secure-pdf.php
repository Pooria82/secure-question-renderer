<?php

return [
    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),
        'host' => env('GOTENBERG_HOST', 'gotenberg'),
    ],
    'chromium' => [
        'path' => env('CHROMIUM_PATH', '/usr/bin/chromium'),
    ],
    'directories' => [
        'private' => storage_path('app/private'),
        'secure_pdfs' => storage_path('app/private/secure_pdfs'),
    ],
];
