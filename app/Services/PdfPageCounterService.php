<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RenderFailureException;

class PdfPageCounterService
{
    /**
     * Determine the number of pages in a PDF file.
     *
     * @throws RenderFailureException
     */
    public function countPages(string $pdfPath): int
    {
        if (! file_exists($pdfPath)) {
            throw new RenderFailureException('PDF file not found for page counting.');
        }

        $pages = 0;

        if (class_exists('\Imagick')) {
            try {
                $imagick = new \Imagick;
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

        return $pages;
    }
}
