<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Factories\ParserFactory;
use App\Services\SecurePdfGenerationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfGenerationPipelineTest extends TestCase
{
    public function test_full_pipeline_dispatch_for_json()
    {
        Bus::fake();

        $pdfService = app(SecurePdfGenerationService::class);
        $parserFactory = app(ParserFactory::class);
        
        $jsonPath = base_path('tests/Fixtures/test.json');
        $parser = $parserFactory->make('json');

        $batchId = $pdfService->generate($parser, $jsonPath, 'test_output.pdf');

        $this->assertNotEmpty($batchId);
        
        Bus::assertBatched(function ($batch) {
            return $batch->name === 'Secure Document Compilation' && $batch->jobs->count() === 2;
        });
    }

    public function test_full_pipeline_dispatch_for_word()
    {
        Bus::fake();

        $pdfService = app(SecurePdfGenerationService::class);
        $parserFactory = app(ParserFactory::class);
        
        $wordPath = base_path('tests/Fixtures/valid.docx');
        if (!file_exists($wordPath)) {
            $this->markTestSkipped('Fixture valid.docx not found.');
        }

        $parser = $parserFactory->make('docx');

        $batchId = $pdfService->generate($parser, $wordPath, 'test_output.pdf');

        $this->assertNotEmpty($batchId);
        
        Bus::assertBatched(function ($batch) {
            return $batch->name === 'Secure Document Compilation' && $batch->jobs->count() === 1;
        });
    }
}
