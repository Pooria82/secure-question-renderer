<?php

declare(strict_types=1);

namespace App\Contracts;

interface PdfRasterizerInterface
{
    /**
     * Rasterizes a PDF file into individual PNG images.
     * Returns an array of generated PNG file paths.
     *
     * @param string $pdfPath
     * @param string $outputDir
     * @return array<string>
     */
    public function rasterize(string $pdfPath, string $outputDir): array;
}
