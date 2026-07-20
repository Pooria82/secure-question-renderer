<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$jobs = Illuminate\Support\Facades\DB::table('failed_jobs')->orderBy('id', 'desc')->take(5)->get();
foreach ($jobs as $job) {
    echo "\n\n=== JOB ID: {$job->id} ===\n";
    $payload = json_decode($job->payload, true);
    echo "Class: " . ($payload['displayName'] ?? 'Unknown') . "\n";
    echo substr($job->exception, 0, 1500);
}
