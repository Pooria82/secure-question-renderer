<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\InputValidationException;
use App\Factories\ParserFactory;
use App\Http\Requests\ConvertFileRequest;
use App\Services\SecurePdfGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SecurePdfController extends Controller
{
    private SecurePdfGenerationService $pdfService;

    private ParserFactory $parserFactory;

    public function __construct(SecurePdfGenerationService $pdfService, ParserFactory $parserFactory)
    {
        $this->pdfService = $pdfService;
        $this->parserFactory = $parserFactory;
    }

    /**
     * Handle the file upload and dispatch PDF generation batch.
     */
    public function convert(ConvertFileRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        try {
            $outputFilename = 'secure_exam_'.time().'.pdf';

            // Store file securely since queue worker/parser needs it after request terminates
            File::ensureDirectoryExists(storage_path('app/private/uploads'));
            File::ensureDirectoryExists(storage_path('app/private/secure_pdfs'));

            $path = $file->storeAs('uploads', uniqid('file_').'.'.$extension, 'local');
            $absolutePath = Storage::disk('local')->path($path);

            $parser = $this->parserFactory->make($extension);

            if (! $parser) {
                return response()->json(['error' => 'Unsupported file format.'], 400);
            }

            $batchId = $this->pdfService->generate($parser, $absolutePath, $outputFilename);

            return response()->json([
                'message' => 'Secure PDF generation has started.',
                'batch_id' => $batchId,
                'status_url' => url("/api/download/{$batchId}"),
                'output_filename' => $outputFilename,
            ], 202);

        } catch (InputValidationException $e) {
            if (isset($absolutePath) && file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            if (isset($absolutePath) && file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
            report($e);

            return response()->json(['error' => 'An unexpected error occurred processing your request.'], 500);
        }
    }

    /**
     * Check batch status and download if complete.
     */
    public function download(string $batchId, Request $request): mixed
    {
        $batch = Bus::findBatch($batchId);

        if (! $batch) {
            return response()->json(['error' => 'Batch not found.'], 404);
        }

        if (! $batch->finished()) {
            return response()->json([
                'status' => 'processing',
                'progress' => $batch->progress(),
            ]);
        }

        if ($batch->hasFailures()) {
            return response()->json(['error' => 'PDF compilation failed during page rendering.'], 500);
        }

        if (\Illuminate\Support\Facades\Cache::get('compile_failed_'.$batchId)) {
            return response()->json(['error' => 'PDF compilation failed during final assembly.'], 500);
        }

        if (\Illuminate\Support\Facades\Cache::get('compiling_'.$batchId)) {
            return response()->json([
                'status' => 'compiling',
                'progress' => 100,
            ]);
        }

        // Normally, the filename should be stored in a database associated with the batch ID.
        // For simplicity here, we assume the client passed the filename or we find the latest.
        // If not passed, we can't reliably guess the filename since it's timestamped.
        $filename = $request->query('filename');
        if (! $filename) {
            return response()->json(['error' => 'Filename query parameter is required to download.'], 400);
        }

        $path = storage_path('app/private/secure_pdfs/'.$filename);

        if (! file_exists($path)) {
            return response()->json(['error' => 'PDF file not found.'], 404);
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }
}
