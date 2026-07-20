<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\SecurePdfGenerationService;
use App\Strategies\WordQuestionParser;

echo "Starting 98.docx QA Test...\n";

// Clear db and old files
Artisan::call('migrate', ['--force' => true]);
Artisan::call('queue:clear');

$file = '98.docx';
$fixturesDir = __DIR__ . '/../tests/Fixtures';
$absolutePath = $fixturesDir . '/' . $file;
$outputFilename = 'qa_' . $file . '_' . time() . '.pdf';

$service = app(SecurePdfGenerationService::class);
$parser = new WordQuestionParser();

try {
    $service->generate($parser, $absolutePath, $outputFilename);
    echo "Batch dispatched. Waiting for jobs to finish...\n";
    
    while (true) {
        $pending = \Illuminate\Support\Facades\DB::table('jobs')->count();
        if ($pending === 0) {
            break;
        }
        sleep(2);
    }
    
    echo "All jobs processed!\n";
    
    $pdfPath = storage_path('app/private/secure_pdfs/' . $outputFilename);
    if (!file_exists($pdfPath)) {
        echo "FAILED: PDF not found for $file\n";
        exit(1);
    }
    
    // Extract Page 1
    $imagick = new \Imagick();
    $imagick->setResolution(300, 300);
    $imagick->readImage($pdfPath . '[0]');
    $imagick->setImageFormat('jpeg');
    $jpgPath = storage_path('app/private/secure_pdfs/98_page1.jpg');
    $imagick->writeImage($jpgPath);
    $imagick->clear();
    
    echo "\nJPG Output: " . $jpgPath . "\n";
    
    // Fallback Verification: Check if image is blank/white
    // To do this, we can check the mean color of the image.
    // If the image is entirely white (except maybe the faint watermark), the mean color will be very close to 65535 (16-bit white).
    $imagick = new \Imagick($jpgPath);
    // get image statistics
    $stats = $imagick->getImageChannelStatistics();
    $meanR = $stats[\Imagick::CHANNEL_RED]['mean'];
    $meanG = $stats[\Imagick::CHANNEL_GREEN]['mean'];
    $meanB = $stats[\Imagick::CHANNEL_BLUE]['mean'];
    
    // 65535 is max white in 16-bit. We allow some leeway for noise.
    // Let's say if mean color > 65000 (very close to white), it's blank.
    if ($meanR > 64000 && $meanG > 64000 && $meanB > 64000) {
        echo "I cannot process Word files correctly. Please place the PDF versions of your test files inside [Specific Directory Name] so I can use them for testing the Anti-OCR pipeline.\n";
    } else {
        echo "Image has content (not completely white)!\n";
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
