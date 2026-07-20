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
            // 1. Convert specific PDF page to high-res PNG using Imagick
            $imagick = new \Imagick();
            $imagick->setResolution(300, 300); // 300 DPI for high fidelity
            // Read only the specific page
            $imagick->readImage($this->pdfPath . '[' . $this->pageIndex . ']');
            
            // Set background to white before flattening (PDFs might have transparent background)
            $imagick->setImageBackgroundColor(new \ImagickPixel('white'));
            $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            
            $imagick->setImageFormat('png');
            $pngBlob = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();

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
