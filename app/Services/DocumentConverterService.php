<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DocumentToHtmlConverterInterface;
use App\Exceptions\RenderFailureException;
use Symfony\Component\Process\Process;

class DocumentConverterService implements DocumentToHtmlConverterInterface
{
    /**
     * Converts a DOCX file to an HTML file using Pandoc.
     *
     * @throws RenderFailureException
     */
    public function convertDocxToHtml(string $inputPath, string $htmlPath): void
    {
        $process = new Process([
            'pandoc',
            $inputPath,
            '-f', 'docx',
            '-t', 'html5',
            '--embed-resources',
            '--standalone',
            '--mathml',
            '-V', 'dir='.config('secure-pdf.processing.pandoc_dir', 'rtl'),
            '-V', 'lang='.config('secure-pdf.processing.pandoc_lang', 'fa'),
            '-o', $htmlPath,
        ]);

        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RenderFailureException('Pandoc conversion failed: '.$process->getErrorOutput());
        }
    }
}
