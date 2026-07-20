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
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class RenderSecureVisualPageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $pdfPath;
    private int $pageIndex;
    private string $tempDir;

    public $timeout = 300; // Increased timeout for Imagick and Intervention
    public $failOnTimeout = true;

    public function __construct(string $pdfPath, int $pageIndex, string $tempDir)
    {
        $this->pdfPath = $pdfPath;
        $this->pageIndex = $pageIndex;
        $this->tempDir = $tempDir;
    }

    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        // Dynamically increase memory limit for high-resolution image processing
        ini_set('memory_limit', '1024M');

        try {
            // 1. Convert specific PDF page to high-res PNG using Ghostscript directly
            // Imagick sometimes fails to render fonts or flatten complex PDFs properly
            $gsPage = $this->pageIndex + 1;
            $pngBlobFile = tempnam(sys_get_temp_dir(), 'gs_') . '.png';
            
            $process = new \Symfony\Component\Process\Process([
                'gs',
                '-q',
                '-dQUIET',
                '-dSAFER',
                '-dBATCH',
                '-dNOPAUSE',
                '-dNOPROMPT',
                '-dMaxBitmap=500000000',
                '-sDEVICE=png16m',
                '-dTextAlphaBits=4',
                '-dGraphicsAlphaBits=4',
                '-r300',
                '-dFirstPage=' . $gsPage,
                '-dLastPage=' . $gsPage,
                '-sOutputFile=' . $pngBlobFile,
                $this->pdfPath
            ]);
            
            $process->setTimeout(300);
            $process->run();
            
            if (!$process->isSuccessful()) {
                throw new \Exception("Ghostscript failed: " . $process->getErrorOutput());
            }
            
            $pngBlob = file_get_contents($pngBlobFile);
            @unlink($pngBlobFile);

            // 2. Apply Anti-OCR and Watermarks using Intervention Image
            $manager = new ImageManager(new Driver());
            $image = $manager->decode($pngBlob);

            // Add diagonal semi-transparent watermark
            $image->text('CONFIDENTIAL - SECURE EXAM', $image->width() / 2, $image->height() / 2, function ($font) {
                $font->color('rgba(255, 0, 0, 0.15)');
                $font->size(80);
                $font->angle(45);
            });

            // Add random noise lines to confuse OCR
            for ($i = 0; $i < 15; $i++) {
                $image->drawLine(function($line) use ($image) {
                    $line->from(rand(0, $image->width()), rand(0, $image->height()));
                    $line->to(rand(0, $image->width()), rand(0, $image->height()));
                    $line->color('rgba(150, 150, 150, 0.3)');
                    $line->width(rand(1, 3));
                });
            }

            // 3. Save the secured page
            // Zero-pad page index for correct alphabetical sorting by CompileSecurePdfJob
            $fileName = sprintf('page_%04d.png', $this->pageIndex);
            $outputPath = $this->tempDir . '/' . $fileName;

            Storage::disk('local')->put($outputPath, (string) $image->encode());

        } catch (\Throwable $e) {
            throw new RenderFailureException('Failed to render secure visual page: ' . $e->getMessage(), 0, $e);
        }
    }
}
