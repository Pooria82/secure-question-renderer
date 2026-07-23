<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$html = \Illuminate\Support\Facades\View::make('questions.batch_render', ['questions' => [
    [
        'text' => '(English) What is your name?',
        'options' => ['(A) John', '(B) Doe', 'C) Something']
    ],
    [
        'text' => 'سوال فارسی با پرانتز (تست)',
        'options' => ['(الف) گزینه یک', 'ب) گزینه دو']
    ]
]])->render();

file_put_contents('C:/Users/Pooria/.gemini/antigravity-cli/brain/2b531490-b814-4f40-a48d-79ad0a4b9e35/test_html_output.html', $html);
echo "HTML written.\n";
