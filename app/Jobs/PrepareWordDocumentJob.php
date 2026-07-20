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

        $disk = Storage::disk('local');
        $tempDirPath = storage_path('app/private/' . $this->tempDir);
        $disk->makeDirectory($this->tempDir);

        // 1. Convert DOCX to PDF using LibreOffice
        $process = new Process([
            'soffice',
            '--headless',
            '--convert-to',
            'pdf',
            $this->inputPath,
            '--outdir',
            $tempDirPath
        ], null, ['HOME' => '/tmp']);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RenderFailureException('LibreOffice conversion failed: ' . $process->getErrorOutput());
        }

        // Get the generated PDF filename
        $filename = pathinfo($this->inputPath, PATHINFO_FILENAME) . '.pdf';
        $pdfPath = $tempDirPath . '/' . $filename;

        if (!file_exists($pdfPath)) {
            throw new RenderFailureException('LibreOffice did not produce the expected PDF file.');
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
        $disk->put($this->tempDir . '/metadata.json', json_encode(['expected_pages' => $pages]));

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
