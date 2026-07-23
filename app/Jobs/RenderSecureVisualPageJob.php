<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\RenderFailureException;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class RenderSecureVisualPageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $pngPath;

    public $timeout = 120; // Fast because GS is extracted out

    public $failOnTimeout = true;

    public function __construct(string $pngPath)
    {
        $this->pngPath = $pngPath;
    }

    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        // Dynamically increase memory limit for high-resolution image processing
        ini_set('memory_limit', '1024M');

        try {
            if (! file_exists($this->pngPath)) {
                throw new RenderFailureException('Extracted PNG file not found: '.$this->pngPath);
            }

            $pngBlob = file_get_contents($this->pngPath);

            // 1. Apply Anti-OCR and Watermarks using Intervention Image
            $manager = new ImageManager(new Driver);
            $image = $manager->decode($pngBlob);

            $watermarkText = config('secure-pdf.security.watermark_text', 'CONFIDENTIAL - SECURE EXAM');
            $watermarkColor = config('secure-pdf.security.watermark_color', 'rgba(255, 0, 0, 0.15)');
            $noiseLines = config('secure-pdf.security.noise_lines', 15);

            // Add diagonal semi-transparent watermark
            $image->text($watermarkText, (int) ($image->width() / 2), (int) ($image->height() / 2), function ($font) use ($watermarkColor) {
                $font->color($watermarkColor);
                $font->size(80);
                $font->angle(45);
            });

            // Add random noise lines to confuse OCR
            for ($i = 0; $i < $noiseLines; $i++) {
                $image->drawLine(function ($line) use ($image) {
                    $line->from(rand(0, $image->width()), rand(0, $image->height()));
                    $line->to(rand(0, $image->width()), rand(0, $image->height()));
                    $line->color('rgba(150, 150, 150, 0.3)');
                    $line->width(rand(1, 3));
                });
            }

            // 2. Overwrite the original PNG securely
            file_put_contents($this->pngPath, (string) $image->encode());

        } catch (\Throwable $e) {
            throw new RenderFailureException('Failed to render secure visual page: '.$e->getMessage(), 0, $e);
        }
    }
}
