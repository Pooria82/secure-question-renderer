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
            '-t', 'html5',
            '--embed-resources',
            '--standalone',
            '--mathml',
            '-V', 'dir=rtl',
            '-V', 'lang=fa',
            '-o', $htmlPath
        ]);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RenderFailureException('Pandoc conversion failed: ' . $process->getErrorOutput());
        }

        $htmlContent = file_get_contents($htmlPath);
        
        // Enforce <html dir="rtl" lang="fa"> and <body dir="rtl"> tag attributes
        $htmlContent = preg_replace('/<html([^>]*)>/i', '<html$1 dir="rtl" lang="fa">', $htmlContent);
        $htmlContent = preg_replace('/<body([^>]*)>/i', '<body$1 dir="rtl">', $htmlContent);

        // Inject custom CSS to enforce RTL alignment, isolate MathML, format tables, and preserve code blocks
        $customCss = <<<CSS
<style>
    /* Global RTL page & typography rules */
    html, body {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 20px !important;
        font-family: 'Amiri', 'Noto Sans Arabic', 'Arial', sans-serif !important;
        direction: rtl !important;
        text-align: right !important;
        overflow: visible !important;
    }
    /* Force RTL direction on content blocks */
    *, p, div, span, table, td, th, h1, h2, h3, h4, h5, h6, li, section, article { 
        direction: rtl !important;
        text-align: right !important; 
        overflow: visible !important;
    }
    /* Right-align tables */
    table { 
        width: 100% !important; 
        display: table !important; 
        overflow: visible !important; 
        margin-left: auto !important;
        margin-right: 0 !important;
    }
    tr { page-break-inside: avoid !important; }
    /* Insulate MathML formulas */
    math, .math, math * { 
        direction: ltr !important; 
        unicode-bidi: embed !important; 
        display: inline-block !important; 
        max-width: 100%; 
        overflow: visible !important; 
    }
    /* Insulate code blocks */
    pre, code, .sourceCode { 
        direction: ltr !important; 
        text-align: left !important; 
        overflow: visible !important; 
        white-space: pre-wrap !important; 
    }
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
            'waitDelay' => '2s',
            'preferCSSPageSize' => true,
            'printBackground' => true,
        ]);

        if ($response->successful()) {
            file_put_contents($pdfPath, $response->body());
        } else {
            throw new RenderFailureException('Gotenberg Chromium conversion failed: ' . $response->body());
        }

        if (!file_exists($pdfPath)) {
            throw new RenderFailureException('Gotenberg Chromium did not produce the expected PDF file.');
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
