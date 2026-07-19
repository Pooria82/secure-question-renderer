<?php

declare(strict_types=1);

namespace App\Services;

use App\Strategies\QuestionParserInterface;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\InputValidationException;

class QuestionProcessingService
{
    private QuestionParserInterface $parser;

    public function __construct(QuestionParserInterface $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Process the given input and return validated question data.
     *
     * @param mixed $input
     * @return array
     * @throws InputValidationException
     */
    public function process(mixed $input): array
    {
        // 1. Parse the input using the injected strategy
        $rawQuestions = $this->parser->parse($input);

        // 2. Validate the extracted data structure securely
        $validator = Validator::make(['questions' => $rawQuestions], [
            'questions' => 'required|array|min:1',
            'questions.*.id' => 'required|string',
            'questions.*.text' => 'required|string',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.options.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new InputValidationException(
                'Question data validation failed: ' . json_encode($validator->errors()->all())
            );
        }

        return $validator->validated()['questions'];
    }
}
