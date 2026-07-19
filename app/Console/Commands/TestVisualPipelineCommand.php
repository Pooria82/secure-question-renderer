<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VisualPdfGenerationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class TestVisualPipelineCommand extends Command
{
    protected $signature = 'test:visual-pipeline';
    protected $description = 'E2E test for the visual pipeline on 98.docx';

    public function handle(VisualPdfGenerationService $service)
    {
        $inputPath = base_path('tests/Fixtures/98.docx');
        
        if (!file_exists($inputPath)) {
            $this->error("File not found: " . $inputPath);
            return;
        }

        $this->info("Starting visual pipeline for " . $inputPath);
        $outputFilename = '98.pdf';
        
        // Ensure old file is deleted
        if (Storage::disk('local')->exists('private/secure_pdfs/' . $outputFilename)) {
            Storage::disk('local')->delete('private/secure_pdfs/' . $outputFilename);
        }

        $batchId = $service->generate($inputPath, $outputFilename);
        $this->info("Dispatched batch " . $batchId . ". Running queue worker...");

        // Run queue worker until empty
        Artisan::call('queue:work', ['--stop-when-empty' => true]);

        // Verify output
        $finalPath = storage_path('app/private/secure_pdfs/' . $outputFilename);
        if (file_exists($finalPath) && filesize($finalPath) > 1000) {
            $this->info("Verified visually rendered PDF: " . $finalPath);
        } else {
            $this->error("Failed to generate or file is too small.");
        }
    }
}
