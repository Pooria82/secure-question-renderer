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
        $pdfPath = $tempDirPath . '/' . $filename . '.pdf';

        $gotenbergUrl = env('GOTENBERG_URL');
        $gotenbergHost = env('GOTENBERG_HOST');
        $gotenbergEndpoint = 'http://gotenberg:3000/forms/libreoffice/convert';

        if (!empty($gotenbergUrl)) {
            $gotenbergEndpoint = str_contains((string) $gotenbergUrl, '/forms/')
                ? (string) $gotenbergUrl
                : rtrim((string) $gotenbergUrl, '/') . '/forms/libreoffice/convert';
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
            $gotenbergEndpoint = rtrim($host, '/') . '/forms/libreoffice/convert';
        } else {
            if (@gethostbyname('gotenberg') === 'gotenberg') {
                $gotenbergEndpoint = 'http://localhost:3000/forms/libreoffice/convert';
            }
        }

        $request = \Illuminate\Support\Facades\Http::timeout(600)->asMultipart();
        $request->attach('files', file_get_contents($resolvedInputPath), basename($resolvedInputPath));

        // Attach all custom fonts dynamically so LibreOffice renders equations, Persian text, and shapes flawlessly
        $fontsDir = storage_path('app/fonts');
        if (is_dir($fontsDir)) {
            $fonts = glob($fontsDir . '/*.{ttf,ttc,otf}', GLOB_BRACE);
            if ($fonts !== false) {
                foreach ($fonts as $font) {
                    $request->attach('files', file_get_contents($font), basename($font));
                }
            }
        }

        try {
            $response = $request->post($gotenbergEndpoint);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if ($gotenbergEndpoint !== 'http://localhost:3000/forms/libreoffice/convert') {
                $response = $request->post('http://localhost:3000/forms/libreoffice/convert');
            } else {
                throw $e;
            }
        }

        if ($response->successful()) {
            file_put_contents($pdfPath, $response->body());
        } else {
            throw new RenderFailureException('Gotenberg LibreOffice conversion failed: ' . $response->body());
        }

        if (!file_exists($pdfPath)) {
            throw new RenderFailureException('Gotenberg LibreOffice did not produce the expected PDF file.');
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
