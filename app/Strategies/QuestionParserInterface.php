<?php

declare(strict_types=1);

namespace App\Strategies;

interface QuestionParserInterface
{
    /**
     * Parse the given input and return a structure containing rendering jobs.
     *
     * @param string $inputPath
     * @param string $tempDir
     * @return array
     */
    public function generateJobs(string $inputPath, string $tempDir): array;
}
