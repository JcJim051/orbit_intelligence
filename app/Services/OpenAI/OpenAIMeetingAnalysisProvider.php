<?php

namespace App\Services\OpenAI;

use App\Contracts\MeetingAnalysisProvider;
use App\Data\MeetingAnalysisResult;
use App\Models\Meeting;
use App\Models\Transcript;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIMeetingAnalysisProvider implements MeetingAnalysisProvider
{
    public function analyze(Meeting $meeting, Transcript $transcript): MeetingAnalysisResult
    {
        $response = Http::withToken(config('meetings.openai.api_key'))
            ->timeout(300)
            ->retry(3, 1000, throw: false)
            ->post(config('meetings.openai.base_url').'/responses', [
                'model' => $this->model(),
                'input' => [[
                    'role' => 'user',
                    'content' => 'Genera un acta objetiva en español. No inventes responsables ni fechas. Reunión: '.$meeting->title."\nParticipantes: ".json_encode($meeting->participants, JSON_UNESCAPED_UNICODE)."\nTranscripción:\n".$transcript->edited_text,
                ]],
                'text' => ['format' => [
                    'type' => 'json_schema',
                    'name' => 'meeting_minutes',
                    'strict' => true,
                    'schema' => $this->schema(),
                ]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI no pudo generar el acta: '.$response->status());
        }

        $output = $response->json('output.0.content.0.text') ?? $response->json('output_text');
        if (! is_string($output)) {
            throw new RuntimeException('OpenAI devolvió una respuesta de acta incompleta.');
        }
        $data = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        return new MeetingAnalysisResult(
            $data['executive_summary'], $data['topics'], $data['decisions'],
            $data['action_items'], $data['risks'], $data['pending_questions'],
            ['request_id' => $response->header('x-request-id')],
        );
    }

    private function schema(): array
    {
        $strings = ['type' => 'array', 'items' => ['type' => 'string']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'executive_summary' => ['type' => 'string'],
                'topics' => $strings,
                'decisions' => $strings,
                'action_items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'description' => ['type' => 'string'],
                            'owner_name' => ['type' => ['string', 'null']],
                            'due_date' => ['type' => ['string', 'null']],
                            'due_date_text' => ['type' => ['string', 'null']],
                            'source_excerpt' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['description', 'owner_name', 'due_date', 'due_date_text', 'source_excerpt'],
                    ],
                ],
                'risks' => $strings,
                'pending_questions' => $strings,
            ],
            'required' => ['executive_summary', 'topics', 'decisions', 'action_items', 'risks', 'pending_questions'],
        ];
    }

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return config('meetings.openai.analysis_model');
    }
}
