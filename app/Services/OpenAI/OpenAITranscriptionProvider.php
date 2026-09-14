<?php

namespace App\Services\OpenAI;

use App\Contracts\TranscriptionProvider;
use App\Data\TranscriptionResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAITranscriptionProvider implements TranscriptionProvider
{
    public function transcribe(string $path, string $mimeType): TranscriptionResult
    {
        $stream = fopen($path, 'r');
        $response = Http::withToken(config('meetings.openai.api_key'))
            ->timeout(1200)
            ->retry(3, 1000, throw: false)
            ->attach('file', $stream, basename($path), ['Content-Type' => $mimeType])
            ->post(config('meetings.openai.base_url').'/audio/transcriptions', [
                'model' => $this->model(),
                'response_format' => 'diarized_json',
                'chunking_strategy' => 'auto',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI no pudo transcribir el audio: '.$response->status());
        }

        $json = $response->json();
        $segments = collect($json['segments'] ?? [])->map(fn (array $segment) => [
            'start_ms' => (int) round(($segment['start'] ?? 0) * 1000),
            'end_ms' => (int) round(($segment['end'] ?? 0) * 1000),
            'speaker' => $segment['speaker'] ?? null,
            'text' => trim($segment['text'] ?? ''),
        ])->all();

        return new TranscriptionResult(
            trim($json['text'] ?? collect($segments)->pluck('text')->implode(' ')),
            $segments,
            $json['language'] ?? null,
            ['request_id' => $response->header('x-request-id')],
        );
    }

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return config('meetings.openai.transcription_model');
    }
}
