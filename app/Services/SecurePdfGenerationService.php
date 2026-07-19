<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\RenderSecureQuestionImageJob;
use App\Jobs\CompileSecurePdfJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Throwable;

class SecurePdfGenerationService
{
    /**
     * Dispatch the job batch to generate a secure PDF.
     *
     * @param array $questions Validated questions array
     * @param string $outputFilename The name of the final PDF file
     * @return string The Batch ID
     * @throws Throwable
     */
    public function generate(array $questions, string $outputFilename): string
    {
        $tempDir = 'temp_renders_' . Str::random(10);
        $jobs = [];

        foreach ($questions as $question) {
            $jobs[] = new RenderSecureQuestionImageJob($question, $tempDir);
        }

        $batch = Bus::batch($jobs)
            ->then(function (\Illuminate\Bus\Batch $batch) use ($tempDir, $outputFilename) {
                // This will execute after all jobs are successfully completed
                dispatch(new CompileSecurePdfJob($tempDir, $outputFilename));
            })
            ->catch(function (\Illuminate\Bus\Batch $batch, Throwable $e) {
                // Handle batch failure if needed
            })
            ->name('Secure Question PDF Compilation')
            ->dispatch();

        return $batch->id;
    }
}
