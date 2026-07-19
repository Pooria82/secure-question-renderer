<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ConvertDocxToVisualPdfJob;
use App\Jobs\CompileSecurePdfJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Throwable;

class VisualPdfGenerationService
{
    /**
     * Dispatch the job batch to generate a secure PDF from a DOCX file.
     *
     * @param string $inputPath The path to the uploaded DOCX file
     * @param string $outputFilename The name of the final PDF file
     * @return string The Batch ID
     * @throws Throwable
     */
    public function generate(string $inputPath, string $outputFilename): string
    {
        $tempDir = 'temp_renders_' . Str::random(10);
        
        $batch = Bus::batch([
            new ConvertDocxToVisualPdfJob($inputPath, $tempDir)
        ])
        ->then(function (\Illuminate\Bus\Batch $batch) use ($tempDir, $outputFilename) {
            // This will execute after all jobs (including those dynamically added) are successfully completed
            dispatch(new CompileSecurePdfJob($tempDir, $outputFilename));
        })
        ->catch(function (\Illuminate\Bus\Batch $batch, Throwable $e) {
            // Handle batch failure if needed
        })
        ->name('Visual Document PDF Compilation')
        ->dispatch();

        return $batch->id;
    }
}
