<?php

declare(strict_types=1);

namespace App\Strategies;

interface QuestionParserInterface
{
    /**
     * Parse the given input into a standardized format.
     *
     * @param mixed $input
     * @return array
     */
    public function parse(mixed $input): array;
}
