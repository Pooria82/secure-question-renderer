<?php

declare(strict_types=1);

namespace App\Strategies;

use App\Exceptions\InputValidationException;
use JsonException;

class JsonQuestionParser implements QuestionParserInterface
{
    /**
     * Parse JSON input into a standardized format.
     *
     * @param mixed $input JSON string
     * @return array
     * @throws InputValidationException
     */
    public function parse(mixed $input): array
    {
        if (!is_string($input)) {
            throw new InputValidationException('JSON parser expects a string input.');
        }

        try {
            $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InputValidationException('Invalid JSON provided: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($data)) {
            throw new InputValidationException('JSON must decode to an array or object.');
        }

        // Basic validation that it's a list of questions
        // In a real scenario, deeper validation of the structure should occur here
        if (!isset($data['questions']) || !is_array($data['questions'])) {
            throw new InputValidationException('Missing "questions" array in JSON.');
        }

        return $data['questions'];
    }
}
