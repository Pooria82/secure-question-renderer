<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$json = json_decode(file_get_contents('tests/Fixtures/mixed_questions_20.json'), true);
$job = new \App\Jobs\PrepareJsonDocumentJob($json['questions'], 'test_manual_job');
$job->handle(app(\App\Services\HtmlSanitizerService::class), app(\App\Services\GotenbergClientService::class), app(\App\Services\PdfPageCounterService::class));
echo "Job executed. PDF generated at storage/app/private/test_manual_job/compiled_json.pdf\n";
