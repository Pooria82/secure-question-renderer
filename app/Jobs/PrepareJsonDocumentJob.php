<?php

declare(strict_types=1);

namespace App\Jobs;

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
        GotenbergClientService $gotenbergService,
        PdfPageCounterService $pageCounterService
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $tempDirPath = storage_path('app/private/'.$this->tempDir);
        File::ensureDirectoryExists($tempDirPath);

        $pdfPath = $tempDirPath.'/compiled_json.pdf';

        // 1. Render all JSON questions to a single HTML document
        $htmlContent = View::make('questions.batch_render', ['questions' => $this->questions])->render();

        // 2. Sanitize HTML using the same service Word relies on
        $htmlContent = $sanitizerService->sanitize($htmlContent);

        // 3. Convert HTML to PDF via Gotenberg
        $gotenbergService->convertHtmlToPdf($htmlContent, $pdfPath);

        // 4. Count PDF pages
        $pages = $pageCounterService->countPages($pdfPath);

        // Write metadata for CompileSecurePdfJob
        file_put_contents($tempDirPath.'/metadata.json', json_encode(['expected_pages' => $pages]));

        // Dispatch a job for each paginated page
        $jobs = [];
        for ($i = 0; $i < $pages; $i++) {
            $jobs[] = new RenderSecureVisualPageJob($pdfPath, $i, $this->tempDir);
        }

        if (! empty($jobs)) {
            $this->batch()?->add($jobs);
        }
    }
}
