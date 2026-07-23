<?php

declare(strict_types=1);

namespace App\Contracts;

interface PdfPageCounterInterface
{
    /**
     * Determine the number of pages in a PDF file.
     */
    public function countPages(string $pdfPath): int;
}
