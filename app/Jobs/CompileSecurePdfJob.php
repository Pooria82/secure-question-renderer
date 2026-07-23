<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\PageCountMismatchException;
use App\Services\PdfPageCounterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Throwable;

class CompileSecurePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $tempDir;

    private string $outputFilename;

    private string $batchId;

    public function __construct(string $tempDir, string $outputFilename, string $batchId = '')
    {
        $this->tempDir = $tempDir;
        $this->outputFilename = $outputFilename;
        $this->batchId = $batchId;
    }

    public function handle(\App\Contracts\PdfPageCounterInterface $pageCounterService): void
    {
        $disk = Storage::disk('local');
        $directoryPath = $this->tempDir;

        ini_set('memory_limit', '1024M');

        try {
            $files = $disk->files($directoryPath);

            // Read expected page count from metadata
            $metadataPath = $directoryPath.'/metadata.json';
            $expectedPages = null;
            if ($disk->exists($metadataPath)) {
                $metadata = json_decode($disk->get($metadataPath), true);
                $expectedPages = $metadata['expected_pages'] ?? null;
            }

            // Filter only PNG images
            $imageFiles = array_filter($files, fn ($file) => str_ends_with($file, '.png'));

            if (empty($imageFiles)) {
                return;
            }

            sort($imageFiles);

            $mpdf = new Mpdf([
                'format' => 'A4',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'tempDir' => storage_path('app/private/mpdf_temp'),
            ]);

            $mpdf->SetProtection(['print', 'print-highres']);

            $index = 0;
            foreach ($imageFiles as $file) {
                if ($index > 0) {
                    $mpdf->AddPage();
                }

                // Force mPDF to mark page as dirty so it doesn't drop the last page when using absolute image positioning
                $mpdf->WriteHTML('<div style="position: absolute; width: 1px; height: 1px; visibility: hidden;">&nbsp;</div>');

                $imagePath = $disk->path($file);
                $mpdf->Image($imagePath, 0, 0, 210, 297, 'png', '', true, false);
                $index++;
            }

            $pdfOutputPath = storage_path('app/private/secure_pdfs/'.$this->outputFilename);
            $disk->makeDirectory('secure_pdfs');
            $mpdf->Output($pdfOutputPath, Destination::FILE);

            // QA Assertion
            if ($expectedPages !== null) {
                $actualPageCount = $pageCounterService->countPages($pdfOutputPath);

                if ($actualPageCount !== $expectedPages) {
                    throw new PageCountMismatchException("Page count mismatch. Expected {$expectedPages}, got {$actualPageCount}.");
                }
            }

        } catch (Throwable $e) {
            if ($this->batchId) {
                \Illuminate\Support\Facades\Cache::put('compile_failed_'.$this->batchId, true, 86400);
            }
            report($e);
            throw $e; // Rethrow to mark job as failed
        } finally {
            if ($this->batchId) {
                \Illuminate\Support\Facades\Cache::forget('compiling_'.$this->batchId);
            }
            if ($disk->exists($directoryPath)) {
                $disk->deleteDirectory($directoryPath);
            }
        }
    }
}
