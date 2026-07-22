<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Exceptions\RenderFailureException;
use Symfony\Component\Process\Process;

class PrepareWordDocumentJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $inputPath;
    private string $tempDir;

    public $timeout = 600; // Increased timeout for LibreOffice
    public $failOnTimeout = true;

    public function __construct(string $inputPath, string $tempDir)
    {
        $this->inputPath = $inputPath;
        $this->tempDir = $tempDir;
    }

    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        $tempDirPath = storage_path('app/private/' . $this->tempDir);
        \Illuminate\Support\Facades\File::ensureDirectoryExists($tempDirPath);

        // 1. Convert DOCX to HTML with MathML using Pandoc, then HTML to PDF via Gotenberg Chromium
        $filename = pathinfo($this->inputPath, PATHINFO_FILENAME);
        $htmlPath = $tempDirPath . '/' . $filename . '.html';
        $pdfPath = $tempDirPath . '/' . $filename . '.pdf';

        $process = new Process([
            'pandoc',
            $this->inputPath,
            '-f', 'docx',
            '-t', 'html',
            '--embed-resources',
            '--standalone',
            '--mathml',
            '-o', $htmlPath
        ]);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RenderFailureException('Pandoc conversion failed: ' . $process->getErrorOutput());
        }

        $htmlContent = file_get_contents($htmlPath);
        
        // Inject custom CSS to fix RTL/LTR alignment and prevent Gotenberg Chromium truncation
        $customCss = <<<CSS
<style>
    /* Override Pandoc's default max-width which causes narrow column truncation */
    html, body {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 20px !important;
        font-family: 'Amiri', 'Noto Sans Arabic', 'Arial', sans-serif !important;
        direction: rtl !important;
        text-align: right !important;
        overflow: visible !important;
    }
    /* Auto-detect text direction based on content for all text elements */
    p, div, span, table, td, th, h1, h2, h3, h4, h5, h6, li { 
        direction: rtl !important;
        text-align: right !important; 
        overflow: visible !important;
    }
    table { width: 100% !important; display: table !important; overflow: visible !important; }
    tr { page-break-inside: avoid !important; }
    /* Ensure math blocks do not break layouts */
    math { max-width: 100%; overflow: visible !important; }
    pre, code, .sourceCode { overflow: visible !important; white-space: pre-wrap !important; }
</style>
CSS;
        $htmlContent = str_replace('</head>', $customCss . "\n</head>", $htmlContent);

        $response = \Illuminate\Support\Facades\Http::timeout(600)->attach(
            'files', $htmlContent, 'index.html'
        )->post('http://gotenberg:3000/forms/chromium/convert/html', [
            'marginTop' => 0,
            'marginBottom' => 0,
            'marginLeft' => 0,
            'marginRight' => 0,
            'waitDelay' => '2s' // Wait for MathML to fully render
        ]);

        if ($response->successful()) {
            file_put_contents($pdfPath, $response->body());
        } else {
            throw new RenderFailureException('Gotenberg conversion failed: ' . $response->body());
        }

        if (!file_exists($pdfPath)) {
            throw new RenderFailureException('Gotenberg did not produce the expected PDF file.');
        }

        // 2. Count pages using Imagick
        try {
            $imagick = new \Imagick();
            $imagick->pingImage($pdfPath);
            $pages = $imagick->getNumberImages();
        } catch (\Exception $e) {
            throw new RenderFailureException('Failed to read PDF pages with Imagick: ' . $e->getMessage());
        }

        // Write metadata for CompileSecurePdfJob
        file_put_contents($tempDirPath . '/metadata.json', json_encode(['expected_pages' => $pages]));

        // 3. Dispatch a job for each page
        $jobs = [];
        for ($i = 0; $i < $pages; $i++) {
            $jobs[] = new RenderSecureVisualPageJob($pdfPath, $i, $this->tempDir);
        }

        if (!empty($jobs)) {
            $this->batch()->add($jobs);
        }
    }
}
