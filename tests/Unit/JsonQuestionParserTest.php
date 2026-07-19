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
        
        $result = $this->parser->parse($validJson);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('q1', $result[0]['id']);
        $this->assertEquals('Question?', $result[0]['text']);
        $this->assertEquals(['A', 'B'], $result[0]['options']);
    }

    public function test_it_throws_exception_on_corrupted_json()
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('Invalid JSON provided');

        $corruptedJson = '{"questions": [{"id": "q1" // missing closing braces';
        $this->parser->parse($corruptedJson);
    }

    public function test_it_throws_exception_if_missing_questions_array()
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('Missing "questions" array in JSON.');

        $missingArray = '{"data": []}';
        $this->parser->parse($missingArray);
    }
}
