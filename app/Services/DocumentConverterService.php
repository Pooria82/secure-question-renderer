<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RenderFailureException;
use Symfony\Component\Process\Process;

class DocumentConverterService
{
    /**
     * Converts a DOCX file to an HTML file using Pandoc.
     *
     * @param string $inputPath
     * @param string $htmlPath
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
            '-V', 'dir=rtl',
            '-V', 'lang=fa',
            '-o', $htmlPath
        ]);
        
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RenderFailureException('Pandoc conversion failed: ' . $process->getErrorOutput());
        }
    }
}
