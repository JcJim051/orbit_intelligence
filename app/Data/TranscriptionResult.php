<?php

namespace App\Data;

final readonly class TranscriptionResult
{
    public function __construct(
        public string $text,
        public array $segments,
        public ?string $language = null,
        public array $metadata = [],
    ) {}
}
