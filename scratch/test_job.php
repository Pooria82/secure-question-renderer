<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $job = new \App\Jobs\RenderSecureQuestionImageJob(['id' => 'q_1', 'text' => 'test', 'options' => ['1']], 'test_dir');
    $job->handle();
    echo "Done!\n";
    if (file_exists(storage_path('app/private/test_dir/question_q_1.png'))) {
        echo "File created successfully!\n";
    } else {
        echo "File NOT created!\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
