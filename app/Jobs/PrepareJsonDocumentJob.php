<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\HtmlToPdfConverterInterface;
use App\Contracts\PdfRasterizerInterface;
use App\Services\HtmlSanitizerService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class PrepareJsonDocumentJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $questions;

    private string $tempDir;

    public $timeout = 600;

    public $failOnTimeout = true;

    public function __construct(array $questions, string $tempDir)
    {
        $this->questions = $questions;
        $this->tempDir = $tempDir;
    }

    public function handle(
        HtmlSanitizerService $sanitizerService,
        HtmlToPdfConverterInterface $gotenbergService,
        PdfRasterizerInterface $rasterizerService
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $tempDirPath = storage_path('app/private/'.$this->tempDir);
        File::ensureDirectoryExists($tempDirPath);

        $pdfPath = $tempDirPath.'/compiled_json.pdf';

        // 1. Render all JSON questions to a single HTML document
        $htmlContent = View::make('questions.batch_render', ['questions' => $this->questions])->render();

        // 2. Convert HTML to PDF via Gotenberg
        // We DO NOT use HtmlSanitizerService here because JSON provides clean data and we want native HTML5 dir handling
        $gotenbergService->convertHtmlToPdf($htmlContent, $pdfPath);

        // 3. Bulk rasterize the entire PDF to PNGs in O(1) Ghostscript operations
        $pngFiles = $rasterizerService->rasterize($pdfPath, $tempDirPath);
        $pages = count($pngFiles);

        // Write metadata for CompileSecurePdfJob
        file_put_contents($tempDirPath.'/metadata.json', json_encode(['expected_pages' => $pages]));

        // Optionally delete the source PDF immediately to save space and security
        @unlink($pdfPath);

        // 4. Dispatch a job for each extracted PNG page to add anti-OCR and watermarking in parallel
        $jobs = [];
        foreach ($pngFiles as $index => $pngFile) {
            $jobs[] = new RenderSecureVisualPageJob($pngFile);
        }

        if (! empty($jobs)) {
            $this->batch()?->add($jobs);
        }
    }
}
