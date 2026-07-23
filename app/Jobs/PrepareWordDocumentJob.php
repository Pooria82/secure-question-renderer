<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\DocumentConverterService;
use App\Services\GotenbergClientService;
use App\Services\HtmlSanitizerService;
use App\Services\PdfPageCounterService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

class PrepareWordDocumentJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $inputPath;

    private string $tempDir;

    public $timeout = 600;

    public $failOnTimeout = true;

    public function __construct(string $inputPath, string $tempDir)
    {
        $this->inputPath = $inputPath;
        $this->tempDir = $tempDir;
    }

    public function handle(
        DocumentConverterService $converterService,
        HtmlSanitizerService $sanitizerService,
        GotenbergClientService $gotenbergService,
        PdfPageCounterService $pageCounterService
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $tempDirPath = storage_path('app/private/'.$this->tempDir);
        File::ensureDirectoryExists($tempDirPath);

        $resolvedInputPath = $this->resolveInputPath($this->inputPath);
        $filename = pathinfo($resolvedInputPath, PATHINFO_FILENAME);
        $htmlPath = $tempDirPath.'/'.$filename.'.html';
        $pdfPath = $tempDirPath.'/'.$filename.'.pdf';

        try {
            // 1. Convert DOCX to HTML
            $converterService->convertDocxToHtml($resolvedInputPath, $htmlPath);

            // 2. Sanitize HTML
            $htmlContent = file_get_contents($htmlPath);
            $htmlContent = $sanitizerService->sanitize($htmlContent);

            // 3. Convert HTML to PDF via Gotenberg
            $gotenbergService->convertHtmlToPdf($htmlContent, $pdfPath);

            // 4. Count PDF pages
            $pages = $pageCounterService->countPages($pdfPath);

            // Write metadata for CompileSecurePdfJob
            file_put_contents($tempDirPath.'/metadata.json', json_encode(['expected_pages' => $pages]));

            // Dispatch a job for each page
            $jobs = [];
            for ($i = 0; $i < $pages; $i++) {
                $jobs[] = new RenderSecureVisualPageJob($pdfPath, $i, $this->tempDir);
            }

            if (! empty($jobs)) {
                $this->batch()?->add($jobs);
            }
        } finally {
            // Clean up the uploaded input file if it was placed in the uploads directory
            if (str_starts_with($resolvedInputPath, storage_path('app/private/uploads')) && File::exists($resolvedInputPath)) {
                File::delete($resolvedInputPath);
            }
        }
    }

    private function resolveInputPath(string $path): string
    {
        if (file_exists($path)) {
            return $path;
        }

        $normalized = str_replace('\\', '/', $path);
        if (preg_match('#(tests/Fixtures/.*)$#', $normalized, $matches)) {
            $candidate = base_path($matches[1]);
            if (file_exists($candidate)) {
                return $candidate;
            }
        } elseif (file_exists(base_path(basename($normalized)))) {
            return base_path(basename($normalized));
        }

        return $path;
    }
}
