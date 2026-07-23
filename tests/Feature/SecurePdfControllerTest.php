<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Jobs\RenderSecureQuestionImageJob;

class SecurePdfControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_it_accepts_valid_json_file_and_dispatches_batch()
    {
        Bus::fake();

        $validJsonPath = base_path('tests/Fixtures/test.json');
        $file = new UploadedFile(
            $validJsonPath,
            'test.json',
            'application/json',
            null,
            true
        );

        $response = $this->postJson('/api/convert', [
            'file' => $file,
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'message',
            'batch_id',
            'status_url',
            'output_filename'
        ]);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 2; // test.json has 2 questions
        });
    }

    public function test_it_accepts_valid_docx_file_and_dispatches_batch()
    {
        Bus::fake();

        $validDocxPath = base_path('tests/Fixtures/valid.docx');
        
        if (!file_exists($validDocxPath)) {
            $this->markTestSkipped('Fixture valid.docx not found.');
        }

        $file = new UploadedFile(
            $validDocxPath,
            'valid.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );

        $response = $this->post('/api/convert', [
            'file' => $file,
        ]);

        $response->assertStatus(202);
        
        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() > 0;
        });
    }

    public function test_it_rejects_invalid_file_types()
    {
        $file = UploadedFile::fake()->create('invalid.txt', 100, 'text/plain');

        $response = $this->postJson('/api/convert', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_it_catches_corrupted_json_and_returns_422()
    {
        $corruptedJsonPath = base_path('tests/Fixtures/corrupted.json');
        $file = new UploadedFile(
            $corruptedJsonPath,
            'corrupted.json',
            'application/json',
            null,
            true
        );

        $response = $this->postJson('/api/convert', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }
}
