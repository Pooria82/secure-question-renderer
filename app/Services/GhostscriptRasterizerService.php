<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PdfRasterizerInterface;
use App\Exceptions\RenderFailureException;
use Symfony\Component\Process\Process;

class GhostscriptRasterizerService implements PdfRasterizerInterface
{
    public function rasterize(string $pdfPath, string $outputDir): array
    {
        $resolution = config('secure-pdf.processing.ghostscript_resolution', 300);
        $outputPattern = $outputDir . '/page_%04d.png';

        $process = new Process([
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
            '-r' . $resolution,
            '-sOutputFile=' . $outputPattern,
            $pdfPath,
        ]);

        $process->setTimeout(600); // 10 minutes for large PDFs
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RenderFailureException('Bulk Ghostscript rasterization failed: ' . $process->getErrorOutput());
        }

        // Retrieve generated PNGs
        $files = glob($outputDir . '/page_*.png');
        if ($files === false) {
            return [];
        }

        sort($files);
        return $files;
    }
}
