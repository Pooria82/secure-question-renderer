<?php

declare(strict_types=1);

namespace App\Contracts;

interface QuestionParserInterface
{
    /**
     * Parse the given input file and return an array of Jobs to be dispatched.
     *
     * @param string $filePath
     * @param string $tempDir
     * @return array
     */
    public function generateJobs(string $filePath, string $tempDir): array;
}
