<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CompileSecurePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $tempDir;
    private string $outputFilename;

    /**
     * Create a new job instance.
     */
    public function __construct(string $tempDir, string $outputFilename)
    {
        $this->tempDir = $tempDir;
        $this->outputFilename = $outputFilename;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $disk = Storage::disk('local');
        $directoryPath = $this->tempDir;

        try {
            $files = $disk->files($directoryPath);
            
            if (empty($files)) {
                return; // Nothing to compile
            }

            // Ensure consistent ordering based on filename/question ID if necessary
            sort($files);

            $mpdf = new \Mpdf\Mpdf([
                'format' => 'A4',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'tempDir' => storage_path('app/private/mpdf_temp') // Use secure temp dir for mpdf
            ]);

            // Explicitly disable text copying, modifying, and extraction at the document level
            // Allow printing (print, print-highres)
            $mpdf->SetProtection(['print', 'print-highres']);

            foreach ($files as $index => $file) {
                if (!str_ends_with($file, '.png')) {
                    continue;
                }

                if ($index > 0) {
                    $mpdf->AddPage();
                }

                $imagePath = storage_path('app/' . $file);
                $mpdf->Image($imagePath, 0, 0, 210, 297, 'png', '', true, false);
            }

            // Save PDF securely
            $pdfOutputPath = storage_path('app/private/secure_pdfs/' . $this->outputFilename);
            $disk->makeDirectory('secure_pdfs');
            $mpdf->Output($pdfOutputPath, \Mpdf\Output\Destination::FILE);

        } catch (Throwable $e) {
            // Log or handle the exception
            report($e);
        } finally {
            // ERROR HANDLING & CLEANUP: Ensure temporary images are automatically deleted
            if ($disk->exists($directoryPath)) {
                $disk->deleteDirectory($directoryPath);
            }
        }
    }
}
