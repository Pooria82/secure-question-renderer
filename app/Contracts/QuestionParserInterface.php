<?php

declare(strict_types=1);

namespace App\Contracts;

interface QuestionParserInterface
{
    /**
     * Parse the given input file and return an array of Jobs to be dispatched.
     */
    public function generateJobs(string $filePath, string $tempDir): array;
}
