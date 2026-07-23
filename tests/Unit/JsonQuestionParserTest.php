<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\InputValidationException;
use App\Strategies\JsonQuestionParser;
use PHPUnit\Framework\TestCase;

class JsonQuestionParserTest extends TestCase
{
    private JsonQuestionParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new JsonQuestionParser();
    }

    public function test_it_correctly_extracts_data_from_valid_json()
    {
        $validJson = '{"questions": [{"id": "q1", "text": "Question?", "options": ["A", "B"]}]}';
        $tempFile = sys_get_temp_dir() . '/test_valid_' . uniqid() . '.json';
        file_put_contents($tempFile, $validJson);

        try {
            $jobs = $this->parser->generateJobs($tempFile, 'test_temp_dir');
            $this->assertIsArray($jobs);
            $this->assertCount(1, $jobs);
            $this->assertInstanceOf(\App\Jobs\RenderSecureQuestionImageJob::class, $jobs[0]);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_it_throws_exception_on_corrupted_json()
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('Invalid JSON provided');

        $corruptedJson = '{"questions": [{"id": "q1" // missing closing braces';
        $tempFile = sys_get_temp_dir() . '/test_corrupt_' . uniqid() . '.json';
        file_put_contents($tempFile, $corruptedJson);

        try {
            $this->parser->generateJobs($tempFile, 'test_temp_dir');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_it_throws_exception_if_missing_questions_array()
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('Missing "questions" array in JSON.');

        $missingArray = '{"data": []}';
        $tempFile = sys_get_temp_dir() . '/test_missing_' . uniqid() . '.json';
        file_put_contents($tempFile, $missingArray);

        try {
            $this->parser->generateJobs($tempFile, 'test_temp_dir');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
