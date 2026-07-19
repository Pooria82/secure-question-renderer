<?php

declare(strict_types=1);

namespace App\Strategies;

use App\Exceptions\InputValidationException;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

class WordQuestionParser implements QuestionParserInterface
{
    /**
     * Parse Word document input into a standardized format.
     *
     * @param mixed $input Path to the Word document
     * @return array
     * @throws InputValidationException
     */
    public function parse(mixed $input): array
    {
        if (!is_string($input) || !file_exists($input)) {
            throw new InputValidationException('Word parser expects a valid file path as input.');
        }

        try {
            $phpWord = IOFactory::load($input);
            $questions = [];
            $currentQuestion = null;

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text = trim($element->getText());
                        $this->processTextLine($text, $questions, $currentQuestion);
                    } elseif (method_exists($element, 'getElements')) {
                        // Handle TextRun
                        $combinedText = '';
                        foreach ($element->getElements() as $subElement) {
                            if (method_exists($subElement, 'getText')) {
                                $combinedText .= $subElement->getText();
                            }
                        }
                        $text = trim($combinedText);
                        $this->processTextLine($text, $questions, $currentQuestion);
                    }
                }
            }

            if ($currentQuestion !== null) {
                $questions[] = $currentQuestion;
            }

            if (empty($questions)) {
                throw new InputValidationException('No valid questions found in the Word document.');
            }

            return $questions;
        } catch (InputValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InputValidationException('Failed to parse Word document: ' . $e->getMessage(), 0, $e);
        }
    }

    private function processTextLine(string $text, array &$questions, ?array &$currentQuestion): void
    {
        if ($text === '') {
            return;
        }

        // Heuristic: if it matches a number followed by a dot, it's a question.
        if (preg_match('/^\d+[\.\)]\s+(.*)/', $text, $matches)) {
            if ($currentQuestion !== null) {
                $questions[] = $currentQuestion;
            }
            $currentQuestion = [
                'id' => uniqid('q_'),
                'text' => trim($matches[1]),
                'options' => [],
            ];
        } elseif ($currentQuestion !== null) {
            // Assume it's an option. Remove typical prefixes like A., b), -, etc.
            $cleanOption = trim(preg_replace('/^([a-zA-Z][\.\)]|[-])\s+/', '', $text));
            if (!empty($cleanOption)) {
                $currentQuestion['options'][] = $cleanOption;
            }
        }
    }
}
