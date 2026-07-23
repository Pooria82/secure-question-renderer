<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$json = json_decode(file_get_contents('tests/Fixtures/mixed_questions_20.json'), true);
$html = \Illuminate\Support\Facades\View::make('questions.batch_render', ['questions' => $json['questions']])->render();

file_put_contents('C:/Users/Pooria/.gemini/antigravity-cli/brain/2b531490-b814-4f40-a48d-79ad0a4b9e35/mixed20.html', $html);
echo "HTML written.\n";
