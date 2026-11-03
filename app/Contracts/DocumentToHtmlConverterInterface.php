<?php

declare(strict_types=1);

namespace App\Contracts;

interface DocumentToHtmlConverterInterface
{
    /**
     * Converts a document (e.g. DOCX) to an HTML file.
     */
    public function convertDocxToHtml(string $inputPath, string $htmlPath): void;
}
