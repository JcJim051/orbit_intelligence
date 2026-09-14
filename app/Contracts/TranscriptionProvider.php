<?php

namespace App\Contracts;

use App\Data\TranscriptionResult;

interface TranscriptionProvider
{
    public function transcribe(string $path, string $mimeType): TranscriptionResult;

    public function name(): string;

    public function model(): string;
}
