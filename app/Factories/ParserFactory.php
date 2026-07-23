<?php

declare(strict_types=1);

namespace App\Factories;

use App\Contracts\QuestionParserInterface;
use App\Strategies\JsonQuestionParser;
use App\Strategies\WordQuestionParser;

class ParserFactory
{
    /**
     * @param string $extension
     * @return QuestionParserInterface|null
     */
    public function make(string $extension): ?QuestionParserInterface
    {
        return match (strtolower($extension)) {
            'json' => app(JsonQuestionParser::class),
            'doc', 'docx' => app(WordQuestionParser::class),
            default => null,
        };
    }
}
