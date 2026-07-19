<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\InputValidationException;
use App\Services\QuestionProcessingService;
use App\Services\SecurePdfGenerationService;
use App\Strategies\JsonQuestionParser;
use App\Strategies\WordQuestionParser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SecurePdfController extends Controller
{
    private SecurePdfGenerationService $pdfService;

    public function __construct(SecurePdfGenerationService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Handle the file upload and dispatch PDF generation batch.
     */
    public function convert(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:json,docx,doc|max:10240',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        try {
            $outputFilename = 'secure_exam_' . time() . '.pdf';

            if ($extension === 'json') {
                $parser = new JsonQuestionParser();
                $input = file_get_contents($file->getRealPath());
                $processor = new QuestionProcessingService($parser);
                $questions = $processor->process($input);
                $batchId = $this->pdfService->generate($questions, $outputFilename);
            } elseif (in_array($extension, ['doc', 'docx'])) {
                // Store file securely since queue worker needs it after request terminates
                $path = $file->storeAs('private/uploads', uniqid('docx_') . '.' . $extension, 'local');
                $absolutePath = storage_path('app/' . $path);
                
                // visual rendering for exact replica
                $visualService = app(\App\Services\VisualPdfGenerationService::class);
                $batchId = $visualService->generate($absolutePath, $outputFilename);
            } else {
                return response()->json(['error' => 'Unsupported file format.'], 400);
            }

            return response()->json([
                'message' => 'Secure PDF generation has started.',
                'batch_id' => $batchId,
                'status_url' => url("/api/download/{$batchId}"),
                'output_filename' => $outputFilename
            ], 202);

        } catch (InputValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check batch status and download if complete.
     */
    public function download(string $batchId, Request $request)
    {
        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            return response()->json(['error' => 'Batch not found.'], 404);
        }

        if (!$batch->finished()) {
            return response()->json([
                'status' => 'processing',
                'progress' => $batch->progress()
            ]);
        }

        if ($batch->hasFailures()) {
            return response()->json(['error' => 'PDF compilation failed during processing.'], 500);
        }

        // Normally, the filename should be stored in a database associated with the batch ID.
        // For simplicity here, we assume the client passed the filename or we find the latest.
        // If not passed, we can't reliably guess the filename since it's timestamped.
        $filename = $request->query('filename');
        if (!$filename) {
            return response()->json(['error' => 'Filename query parameter is required to download.'], 400);
        }

        $path = storage_path('app/private/secure_pdfs/' . $filename);

        if (!file_exists($path)) {
            return response()->json(['error' => 'PDF file not found.'], 404);
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
