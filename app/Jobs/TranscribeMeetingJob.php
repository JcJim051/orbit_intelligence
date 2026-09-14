<?php

namespace App\Jobs;

use App\Contracts\TranscriptionProvider;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TranscribeMeetingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 7200;

    public function __construct(public string $meetingId)
    {
        $this->onQueue('audio');
    }

    public function handle(TranscriptionProvider $provider, AuditLogger $audit): void
    {
        $meeting = Meeting::with('files')->findOrFail($this->meetingId);
        $meeting->update(['status' => MeetingStatus::Transcribing, 'error_stage' => null, 'error_message' => null]);
        $audit->log($meeting, 'processing.started', null, ['provider' => $provider->name(), 'model' => $provider->model()], 'transcription');

        $chunks = $meeting->files->where('kind', 'chunk')->whereNull('deleted_at')->sortBy('sequence');
        $sources = $chunks->isNotEmpty() ? $chunks : $meeting->files->where('kind', 'original');
        $allText = [];
        $allSegments = [];
        $language = null;
        $metadata = [];

        foreach ($sources as $source) {
            $result = $provider->transcribe(Storage::disk($source->disk)->path($source->path), $source->mime_type);
            $allText[] = $result->text;
            $language ??= $result->language;
            $metadata[] = $result->metadata;
            foreach ($result->segments as $segment) {
                $allSegments[] = [
                    'meeting_file_id' => $source->id,
                    'start_ms' => $source->offset_ms + $segment['start_ms'],
                    'end_ms' => $source->offset_ms + $segment['end_ms'],
                    'speaker_key' => $segment['speaker'] ?? null,
                    'text' => $segment['text'],
                ];
            }
        }

        DB::transaction(function () use ($meeting, $provider, $allText, $allSegments, $language, $metadata) {
            $meeting->transcript?->delete();
            $text = trim(implode("\n", $allText));
            $transcript = Transcript::create([
                'meeting_id' => $meeting->id,
                'provider' => $provider->name(), 'model' => $provider->model(), 'language' => $language,
                'original_text' => $text, 'edited_text' => $text, 'speaker_map' => [], 'provider_metadata' => $metadata,
            ]);
            foreach ($allSegments as $sequence => $segment) {
                TranscriptSegment::create([
                    'transcript_id' => $transcript->id, 'sequence' => $sequence,
                    'meeting_file_id' => $segment['meeting_file_id'], 'start_ms' => $segment['start_ms'],
                    'end_ms' => $segment['end_ms'], 'speaker_key' => $segment['speaker_key'],
                    'original_text' => $segment['text'], 'edited_text' => $segment['text'],
                ]);
            }
        });

        foreach ($chunks as $chunk) {
            Storage::disk($chunk->disk)->delete($chunk->path);
            $chunk->update(['deleted_at' => now()]);
        }
        $audit->log($meeting, 'processing.completed', null, ['segments' => count($allSegments)], 'transcription');
        AnalyzeMeetingJob::dispatch($meeting->id);
    }

    public function failed(Throwable $e): void
    {
        $meeting = Meeting::find($this->meetingId);
        $meeting?->update(['status' => MeetingStatus::Error, 'error_stage' => 'transcription', 'error_message' => str($e->getMessage())->limit(500)]);
        if ($meeting) {
            app(AuditLogger::class)->log($meeting, 'processing.failed', null, ['message' => str($e->getMessage())->limit(500)->toString()], 'transcription');
        }
    }
}
