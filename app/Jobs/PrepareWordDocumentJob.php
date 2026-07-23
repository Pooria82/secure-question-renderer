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
        $resolvedInputPath = $this->inputPath;
        if (!file_exists($resolvedInputPath)) {
            $normalized = str_replace('\\', '/', $resolvedInputPath);
            if (preg_match('#(tests/Fixtures/.*)$#', $normalized, $matches)) {
                $candidate = base_path($matches[1]);
                if (file_exists($candidate)) {
                    $resolvedInputPath = $candidate;
                }
            } elseif (file_exists(base_path(basename($normalized)))) {
                $resolvedInputPath = base_path(basename($normalized));
            }
        }

        $filename = pathinfo($resolvedInputPath, PATHINFO_FILENAME);
        $htmlPath = $tempDirPath . '/' . $filename . '.html';
        $pdfPath = $tempDirPath . '/' . $filename . '.pdf';

        $process = new Process([
            'pandoc',
            $resolvedInputPath,
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
        $htmlContent = preg_replace_callback('/<html([^>]*)>/i', function ($matches) {
            $attrs = $matches[1];
            $attrs = preg_replace('/\s*(dir|lang|xml:lang)=("[^"]*"|\'[^\']*\')/i', '', $attrs);
            return '<html' . $attrs . ' dir="rtl" lang="fa">';
        }, $htmlContent);

        $htmlContent = preg_replace_callback('/<body([^>]*)>/i', function ($matches) {
            $attrs = $matches[1];
            if (stripos($attrs, 'dir=') === false) {
                return '<body' . $attrs . ' dir="rtl">';
            }
            return $matches[0];
        }, $htmlContent);

        // Also enforce dir="rtl" on block elements to prevent Chromium bidi scrambling when mixing math (LTR) and text (RTL)
        $htmlContent = preg_replace_callback('/<(p|div|li|td|th)([^>]*)>/i', function ($matches) {
            $tag = $matches[1];
            $attrs = $matches[2];
            $attrs = preg_replace('/\s*dir=("[^"]*"|\'[^\']*\')/i', '', $attrs);
            return '<' . $tag . $attrs . ' dir="rtl">';
        }, $htmlContent);

        // Isolate LTR text (English, plain text math formulas like f(x)=0) inside RTL paragraphs to prevent Chromium BIDI scrambling.
        // We only target text nodes (content between > and <) to avoid breaking HTML tags or attributes.
        // We MUST skip <math> blocks completely so we don't inject spans into MathML elements and break them.
        $parts = preg_split('/(<math\b.*?>.*?<\/math>)/is', $htmlContent, -1, PREG_SPLIT_DELIM_CAPTURE);
        $htmlContent = "";
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                // Outside math tags, apply our text node regex
                $part = preg_replace_callback('/(>)([^<]+)(<)/', function ($matches) {
                    $text = $matches[2];
                    // Match sequences starting/ending with alphanumeric, containing allowed punctuation in between
                    $text = preg_replace('/([a-zA-Z0-9][a-zA-Z0-9\(\)\=\+\-\*\/\.\, ]*[a-zA-Z0-9]|[a-zA-Z0-9])/i', '<span dir="ltr" style="unicode-bidi: embed;">$1</span>', $text);
                    return $matches[1] . $text . $matches[3];
                }, $part);
            }
            $htmlContent .= $part;
        }

        // Inject custom CSS to enforce RTL alignment, isolate MathML, format tables, and preserve code blocks
        $customCss = <<<CSS
<style>
    /* Global RTL page & typography rules */
    html, body {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 20px !important;
        font-family: 'Amiri', 'Noto Sans Arabic', sans-serif !important;
        direction: rtl !important;
        text-align: right !important;
        overflow: visible !important;
    }
    /* Force RTL direction on content blocks */
    html, body, p, div, span, table, td, th, h1, h2, h3, h4, h5, h6, li, section, article { 
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
        text-align: right !important;
    }
    tr { page-break-inside: avoid !important; }
    /* Insulate MathML formulas */
    math { 
        direction: ltr !important; 
        unicode-bidi: embed !important; 
        text-align: initial !important;
        max-width: 100%; 
        overflow: visible !important; 
    }
    /* Specifically ensure annotations (raw latex) remain hidden */
    annotation {
        display: none !important;
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

        $gotenbergUrl = env('GOTENBERG_URL');
        $gotenbergHost = env('GOTENBERG_HOST');

        if (!empty($gotenbergUrl)) {
            $gotenbergEndpoint = str_contains((string) $gotenbergUrl, '/forms/')
                ? (string) $gotenbergUrl
                : rtrim((string) $gotenbergUrl, '/') . '/forms/chromium/convert/html';
            if (!str_starts_with($gotenbergEndpoint, 'http://') && !str_starts_with($gotenbergEndpoint, 'https://')) {
                $gotenbergEndpoint = 'http://' . $gotenbergEndpoint;
            }
        } elseif (!empty($gotenbergHost)) {
            $host = (string) $gotenbergHost;
            if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
                $host = 'http://' . $host;
            }
            if (!preg_match('/:\d+$/', parse_url($host, PHP_URL_HOST) ?? parse_url($host, PHP_URL_PATH) ?? '') && !str_contains(substr($host, 7), ':')) {
                $host = rtrim($host, '/') . ':3000';
            }
            $gotenbergEndpoint = rtrim($host, '/') . '/forms/chromium/convert/html';
        } else {
            $gotenbergEndpoint = 'http://gotenberg:3000/forms/chromium/convert/html';
            if (@gethostbyname('gotenberg') === 'gotenberg') {
                $gotenbergEndpoint = 'http://localhost:3000/forms/chromium/convert/html';
            }
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(600)->attach(
                'files', $htmlContent, 'index.html'
            )->post($gotenbergEndpoint, [
                'marginTop' => 0,
                'marginBottom' => 0,
                'marginLeft' => 0,
                'marginRight' => 0,
                'waitDelay' => '2s',
                'preferCSSPageSize' => true,
                'printBackground' => true,
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if ($gotenbergEndpoint !== 'http://localhost:3000/forms/chromium/convert/html') {
                $response = \Illuminate\Support\Facades\Http::timeout(600)->attach(
                    'files', $htmlContent, 'index.html'
                )->post('http://localhost:3000/forms/chromium/convert/html', [
                    'marginTop' => 0,
                    'marginBottom' => 0,
                    'marginLeft' => 0,
                    'marginRight' => 0,
                    'waitDelay' => '2s',
                    'preferCSSPageSize' => true,
                    'printBackground' => true,
                ]);
            } else {
                throw $e;
            }
        }

        if ($response->successful()) {
            file_put_contents($pdfPath, $response->body());
        } else {
            throw new RenderFailureException('Gotenberg Chromium conversion failed: ' . $response->body());
        }

        if (!file_exists($pdfPath)) {
            throw new RenderFailureException('Gotenberg Chromium did not produce the expected PDF file.');
        }

        // 2. Count pages
        $pages = 0;
        if (class_exists('\Imagick')) {
            try {
                $imagick = new \Imagick();
                $imagick->pingImage($pdfPath);
                $pages = $imagick->getNumberImages();
            } catch (\Throwable $e) {
                $pages = 0;
            }
        }

        if ($pages === 0) {
            $pdfBytes = file_get_contents($pdfPath);
            if (preg_match('/\/Count\s+(\d+)/', $pdfBytes, $matches)) {
                $pages = (int) $matches[1];
            } else {
                $pages = preg_match_all('/\/Type\s*\/Page\b/', $pdfBytes);
            }
        }

        if ($pages === 0) {
            throw new RenderFailureException('Failed to read PDF page count.');
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
