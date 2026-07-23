<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$parser = app(App\Strategies\JsonQuestionParser::class);
$jobs = $parser->generateJobs(base_path('test.json'), 'test_json_manual');

foreach ($jobs as $job) {
    app()->call([$job, 'handle']);
}
echo "Done\n";
