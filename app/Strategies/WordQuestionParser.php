<?php

declare(strict_types=1);

namespace App\Strategies;

use App\Exceptions\InputValidationException;
use Throwable;
use ZipArchive;

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
            $text = $this->extractTextFromDocx($input);
            $lines = explode("\n", $text);
            
            $questions = [];
            $currentQuestion = null;

            foreach ($lines as $line) {
                $this->processTextLine(trim($line), $questions, $currentQuestion);
            }

            if ($currentQuestion !== null && count($currentQuestion['options']) >= 2) {
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

    private function extractTextFromDocx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $content = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($content !== false) {
                // Replace paragraphs with newlines
                $content = str_replace('</w:p>', "\n", $content);
                // Strip all XML tags
                $text = strip_tags($content);
                return $text;
            }
        }
        throw new \Exception('Could not read word/document.xml from docx archive.');
    }

    private function processTextLine(string $text, array &$questions, ?array &$currentQuestion): void
    {
        if ($text === '') {
            return;
        }

        // Detect Question: number followed by hyphen or dot (e.g., "36- ", "1. ")
        // We explicitly exclude ")" here to avoid matching options like "1)"
        if (preg_match('/^\d+\s*[-\.]\s+(.*)/', $text, $matches)) {
            if ($currentQuestion !== null) {
                // Only add if it has options, otherwise it might be a false positive
                if (count($currentQuestion['options']) >= 2) {
                    $questions[] = $currentQuestion;
                }
            }
            $currentQuestion = [
                'id' => uniqid('q_'),
                'text' => trim($matches[1]),
                'options' => [],
            ];
        } elseif ($currentQuestion !== null) {
            // Detect Option: "1)", "2)", "A.", "الف)"
            if (preg_match('/^(\d+[\)]|[a-zA-Zپچجحخعغفقثصضشسیبلاتنمکگوء][\.\)])\s+(.*)/u', $text, $matches)) {
                $cleanOption = trim($matches[2]);
                if (!empty($cleanOption)) {
                    $currentQuestion['options'][] = $cleanOption;
                }
            } else {
                // If it's not a new option, it could be a continuation of the question text
                // or part of the answer/explanation (which we ignore if options already exist).
                if (empty($currentQuestion['options'])) {
                    $currentQuestion['text'] .= "\n" . trim($text);
                }
            }
        }
    }
}
