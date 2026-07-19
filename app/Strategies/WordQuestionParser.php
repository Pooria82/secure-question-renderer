<?php

declare(strict_types=1);

namespace App\Strategies;

use App\Exceptions\InputValidationException;

class WordQuestionParser implements QuestionParserInterface
{
    /**
     * Parse Word document input into a standardized format.
     * Stub for demonstrating extensibility.
     *
     * @param mixed $input Path or content of the Word document
     * @return array
     * @throws InputValidationException
     */
    public function parse(mixed $input): array
    {
        // TODO: Implement parsing of docx files
        // E.g., using PhpWord to extract questions and options
        
        throw new InputValidationException('Word parsing is not yet implemented.');
    }
}
