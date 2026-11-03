<?php

declare(strict_types=1);

namespace App\Strategies;

use App\Contracts\QuestionParserInterface;
use App\Exceptions\InputValidationException;
use App\Jobs\PrepareWordDocumentJob;

class WordQuestionParser implements QuestionParserInterface
{
    /**
     * Parse Word document input and dispatch background processing job.
     *
     * @param  string  $inputPath  Path to the Word document
     * @param  string  $tempDir  Temporary directory for processing
     *
     * @throws InputValidationException
     */
    public function generateJobs(string $inputPath, string $tempDir): array
    {
        if (! file_exists($inputPath)) {
            throw new InputValidationException('Word parser expects a valid file path as input.');
        }

        // We dispatch PrepareWordDocumentJob to handle LibreOffice and Imagick page counting asynchronously
        return [
            new PrepareWordDocumentJob($inputPath, $tempDir),
        ];
    }
}
