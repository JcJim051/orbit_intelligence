<?php

namespace App\Services;

use App\Contracts\TranscriptionProvider;
use App\Data\TranscriptionResult;

class FakeTranscriptionProvider implements TranscriptionProvider
{
    public function transcribe(string $path, string $mimeType): TranscriptionResult
    {
        return new TranscriptionResult(
            'Transcripción de demostración. Configure OPENAI_API_KEY para procesar audio real.',
            [[
                'start_ms' => 0,
                'end_ms' => 5000,
                'speaker' => 'Hablante A',
                'text' => 'Transcripción de demostración. Configure OPENAI_API_KEY para procesar audio real.',
            ]],
            'es',
            ['fake' => true],
        );
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-transcription';
    }
}
