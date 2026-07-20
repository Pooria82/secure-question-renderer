<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\SecurePdfGenerationService;
use App\Strategies\WordQuestionParser;
use App\Strategies\JsonQuestionParser;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

echo "Starting Fallback Verification Script...\n";

// Ensure DB is migrated for job batching
Artisan::call('migrate', ['--force' => true]);
Artisan::call('queue:clear');

$fixturesDir = __DIR__ . '/../tests/Fixtures';
$files = scandir($fixturesDir);
$files = array_filter($files, function($f) {
    return !in_array($f, ['.', '..']);
});

$service = app(SecurePdfGenerationService::class);
$batches = [];

foreach ($files as $file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $absolutePath = $fixturesDir . '/' . $file;
    $outputFilename = 'fallback_' . $file . '_' . time() . '.pdf';

    try {
        $parser = match($extension) {
            'json' => new JsonQuestionParser(),
            'doc', 'docx' => new WordQuestionParser(),
            default => null,
        };

        if (!$parser) {
            echo "Skipping $file: unsupported format.\n";
            continue;
        }

        echo "Dispatching batch for $file...\n";
        $batchId = $service->generate($parser, $absolutePath, $outputFilename);
        $batches[$batchId] = [
            'file' => $file,
            'output_pdf' => storage_path('app/private/secure_pdfs/' . $outputFilename)
        ];
    } catch (\Throwable $e) {
        echo "Error dispatching $file: " . $e->getMessage() . "\n";
    }
}

echo "Waiting for background queues to process...\n";

// Poll until jobs are done
while (true) {
    $pending = \Illuminate\Support\Facades\DB::table('jobs')->count();
    if ($pending === 0) {
        break;
    }
    echo "Pending jobs: $pending. Waiting 2 seconds...\n";
    sleep(2);
}

echo "All jobs processed!\n\n";

$imagick = new \Imagick();
foreach ($batches as $batchId => $data) {
    $pdfPath = $data['output_pdf'];
    if (file_exists($pdfPath)) {
        echo "SUCCESS: PDF generated for {$data['file']} -> {$pdfPath}\n";
        
        // Extract Page 1 for 98.docx
        if ($data['file'] === '98.docx') {
            try {
                $imagick->setResolution(300, 300);
                $imagick->readImage($pdfPath . '[0]'); // Page 1
                $imagick->setImageFormat('jpeg');
                $jpgPath = storage_path('app/private/secure_pdfs/98_page1.jpg');
                $imagick->writeImage($jpgPath);
                $imagick->clear();
                echo "\n*** VISUAL VERIFICATION TARGET ***\n";
                echo "JPG Output: " . $jpgPath . "\n";
                echo "PDF Output: " . $pdfPath . "\n";
                echo "**********************************\n";
            } catch (\Exception $e) {
                echo "Failed to extract JPG for 98.docx: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "FAILED: PDF not found for {$data['file']}\n";
    }
}

$imagick->destroy();
echo "\nScript finished.\n";
