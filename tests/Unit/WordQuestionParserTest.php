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
        // Use the real valid.docx file provided in Fixtures
        $fixturePath = base_path('tests/Fixtures/valid.docx');
        
        // Skip if file doesn't exist to prevent failure in pure isolation
        if (!file_exists($fixturePath)) {
            $this->markTestSkipped('Fixture valid.docx not found.');
        }

        $result = $this->parser->parse($fixturePath);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        
        // Assert structure of the first question matches our expectation
        $firstQuestion = $result[0];
        $this->assertArrayHasKey('id', $firstQuestion);
        $this->assertArrayHasKey('text', $firstQuestion);
        $this->assertArrayHasKey('options', $firstQuestion);
        $this->assertIsArray($firstQuestion['options']);
    }

    public function test_it_throws_exception_on_corrupted_word_document()
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('Failed to parse Word document');

        // Create a fake corrupted docx (just a text file masquerading as docx)
        $corruptedPath = base_path('tests/Fixtures/corrupted_fake.docx');
        file_put_contents($corruptedPath, 'This is not a zip/docx file.');

        try {
            $this->parser->parse($corruptedPath);
        } finally {
            unlink($corruptedPath);
        }
    }

    public function test_it_throws_exception_if_file_does_not_exist()
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('Word parser expects a valid file path as input.');

        $this->parser->parse(base_path('tests/Fixtures/non_existent.docx'));
    }
}
