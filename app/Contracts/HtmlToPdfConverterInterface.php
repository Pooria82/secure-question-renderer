<?php

declare(strict_types=1);

namespace App\Contracts;

interface HtmlToPdfConverterInterface
{
    /**
     * Converts HTML string to PDF.
     */
    public function convertHtmlToPdf(string $htmlContent, string $pdfPath): void;
}
