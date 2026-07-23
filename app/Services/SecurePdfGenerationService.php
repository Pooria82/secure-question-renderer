<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\QuestionParserInterface;
use App\Jobs\CompileSecurePdfJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SecurePdfGenerationService
{
    /**
     * Dispatch the job batch to generate a secure PDF.
     *
     * @param  string  $inputPath  Path to the input file
     * @param  string  $outputFilename  The name of the final PDF file
     * @return string The Batch ID
     *
     * @throws Throwable
     */
    public function generate(QuestionParserInterface $parser, string $inputPath, string $outputFilename): string
    {
        $tempDir = 'temp_renders_'.Str::random(10);
        Storage::disk('local')->makeDirectory($tempDir);

        try {
            // Generate jobs using strategy pattern
            $jobs = $parser->generateJobs($inputPath, $tempDir);

            $batch = Bus::batch($jobs)
                ->then(function (Batch $batch) use ($tempDir, $outputFilename) {
                    // This will execute after all jobs are successfully completed
                    \Illuminate\Support\Facades\Cache::put('compiling_'.$batch->id, true, 86400);
                    dispatch(new CompileSecurePdfJob($tempDir, $outputFilename, $batch->id));
                })
                ->catch(function (Batch $batch, Throwable $e) use ($tempDir) {
                    // Handle batch failure gracefully and clean up orphaned temporary directory
                    if (Storage::disk('local')->exists($tempDir)) {
                        Storage::disk('local')->deleteDirectory($tempDir);
                    }
                })
                ->name('Secure Document Compilation')
                ->dispatch();

            return $batch->id;
        } catch (Throwable $e) {
            if (Storage::disk('local')->exists($tempDir)) {
                Storage::disk('local')->deleteDirectory($tempDir);
            }
            throw $e;
        }
    }
}
