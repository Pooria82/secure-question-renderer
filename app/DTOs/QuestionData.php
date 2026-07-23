<?php

declare(strict_types=1);

namespace App\DTOs;

class QuestionData
{
    public function __construct(
        public readonly string $id,
        public readonly string $text,
        public readonly array $options
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? uniqid('q_'),
            $data['text'] ?? '',
            $data['options'] ?? []
        );
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'options' => $this->options,
        ];
    }
}
