<?php

declare(strict_types=1);

namespace App\Strategies;

use App\Contracts\QuestionParserInterface;
use App\DTOs\QuestionData;
use App\Exceptions\InputValidationException;
use App\Jobs\PrepareJsonDocumentJob;
use JsonException;

class JsonQuestionParser implements QuestionParserInterface
{
    /**
     * Parse JSON input and dispatch jobs.
     *
     * @param  string  $inputPath  Path to JSON file
     * @param  string  $tempDir  Temporary directory
     *
     * @throws InputValidationException
     */
    public function generateJobs(string $inputPath, string $tempDir): array
    {
        if (! file_exists($inputPath)) {
            throw new InputValidationException('JSON parser expects a valid file path.');
        }

        $input = file_get_contents($inputPath);
        if (! is_string($input)) {
            throw new InputValidationException('JSON parser expects a string input.');
        }

        try {
            $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InputValidationException('Invalid JSON provided: '.$e->getMessage(), 0, $e);
        }

        if (! is_array($data) || ! isset($data['questions']) || ! is_array($data['questions'])) {
            throw new InputValidationException('Missing "questions" array in JSON.');
        }

        $questions = $data['questions'];

        $jobs = [];
        $validQuestions = [];
        foreach ($questions as $questionArray) {
            $questionDto = QuestionData::fromArray($questionArray);
            $validQuestions[] = $questionDto->toArray();
        }

        if (! empty($validQuestions)) {
            $jobs[] = new PrepareJsonDocumentJob($validQuestions, $tempDir);
        }

        return $jobs;
    }
}
