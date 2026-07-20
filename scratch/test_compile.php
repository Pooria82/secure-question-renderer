<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$disk = \Illuminate\Support\Facades\Storage::disk('local');
$files = $disk->files('private/test_dir');
print_r($files);
