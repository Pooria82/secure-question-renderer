<?php

return [
    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),
        'host' => env('GOTENBERG_HOST', 'gotenberg'),
        'wait_delay' => env('GOTENBERG_WAIT_DELAY', '2s'),
    ],
    'chromium' => [
        'path' => env('CHROMIUM_PATH', '/usr/bin/chromium'),
    ],
    'directories' => [
        'private' => storage_path('app/private'),
        'secure_pdfs' => storage_path('app/private/secure_pdfs'),
    ],
    'security' => [
        'watermark_text' => env('SECURE_PDF_WATERMARK', 'CONFIDENTIAL - SECURE EXAM'),
        'watermark_color' => env('SECURE_PDF_WATERMARK_COLOR', 'rgba(255, 0, 0, 0.15)'),
        'noise_lines' => (int) env('SECURE_PDF_NOISE_LINES', 15),
    ],
    'processing' => [
        'ghostscript_resolution' => (int) env('GS_RESOLUTION', 300),
        'pandoc_dir' => env('PANDOC_DIR', 'rtl'),
        'pandoc_lang' => env('PANDOC_LANG', 'fa'),
    ],
];
