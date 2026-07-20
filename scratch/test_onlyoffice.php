<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

$inputPath = __DIR__.'/../tests/Fixtures/98.docx';
$tempFile = 'onlyoffice_test.docx';

// Copy file to public storage
Storage::disk('public')->put($tempFile, file_get_contents($inputPath));
$url = 'http://nginx/storage/' . $tempFile;

echo "Waiting 10s for OnlyOffice to initialize...\n";
sleep(10); // Give OnlyOffice a bit more time to start up completely

echo "Sending conversion request to OnlyOffice...\n";
$response = Http::post('http://onlyoffice/ConvertService.ashx', [
    'async' => false,
    'filetype' => 'docx',
    'key' => uniqid(),
    'outputtype' => 'pdf',
    'title' => 'output.pdf',
    'url' => $url
]);

if ($response->successful()) {
    $data = $response->json();
    if (isset($data['fileUrl'])) {
        echo "Conversion successful! Downloading PDF from: " . $data['fileUrl'] . "\n";
        $pdfData = file_get_contents($data['fileUrl']);
        file_put_contents(__DIR__.'/../storage/app/private/onlyoffice_test.pdf', $pdfData);
        echo "PDF saved to storage/app/private/onlyoffice_test.pdf\n";
    } else {
        echo "Conversion returned successful status but no fileUrl: " . $response->body() . "\n";
    }
} else {
    echo "Conversion failed: " . $response->body() . "\n";
}

Storage::disk('public')->delete($tempFile);
