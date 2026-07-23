<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\InputValidationException;
use App\Strategies\WordQuestionParser;
use PHPUnit\Framework\TestCase;

class WordQuestionParserTest extends TestCase
{
    private WordQuestionParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new WordQuestionParser();
    }

    public function test_it_correctly_extracts_data_from_valid_word_document()
    {
        $fixturePath = base_path('tests/Fixtures/98.docx');
        if (!file_exists($fixturePath)) {
            $fixturePath = base_path('tests/Fixtures/valid.docx');
        }
        
        if (!file_exists($fixturePath)) {
            $this->markTestSkipped('Fixture docx file not found.');
        }

        $jobs = $this->parser->generateJobs($fixturePath, 'temp_test_dir');

        $this->assertIsArray($jobs);
        $this->assertCount(1, $jobs);
        $this->assertInstanceOf(\App\Jobs\PrepareWordDocumentJob::class, $jobs[0]);
    }

    public function test_it_throws_exception_if_file_does_not_exist()
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('Word parser expects a valid file path as input.');

        $this->parser->generateJobs(base_path('tests/Fixtures/non_existent.docx'), 'temp_test_dir');
    }
}
