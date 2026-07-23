<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$html = \Illuminate\Support\Facades\View::make('questions.batch_render', ['questions' => [
    [
        'text' => '<p dir="rtl" align="right" style="text-align: right; direction:rtl;">37. Which sentence has the word with a different vowel sound than the others?</p>',
        'options' => [
            '<p dir="rtl" align="right" style="text-align: right;">(A) There were ten eggs in the nest.</p>',
            '<p dir="rtl" align="right" style="text-align: right;">(B) My desk is next to the bed.</p>'
        ]
    ]
]])->render();

file_put_contents('C:/Users/Pooria/.gemini/antigravity-cli/brain/2b531490-b814-4f40-a48d-79ad0a4b9e35/test_html_output5.html', $html);
echo "HTML written.\n";
