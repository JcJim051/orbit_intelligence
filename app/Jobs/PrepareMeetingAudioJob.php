<?php

namespace App\Jobs;

use App\Contracts\AudioInspector;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingFile;
use App\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PrepareMeetingAudioJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 1800;

    public function __construct(public string $meetingId)
    {
        $this->onQueue('audio');
    }

    public function handle(AudioInspector $inspector, AuditLogger $audit): void
    {
        $meeting = Meeting::findOrFail($this->meetingId);
        $original = $meeting->files()->where('kind', 'original')->firstOrFail();
        $audit->log($meeting, 'processing.started', null, [], 'prepare_audio');

        if ($original->size_bytes > config('meetings.chunk_max_bytes')) {
            $directory = "meetings/{$meeting->user_id}/{$meeting->id}/chunks";
            Storage::disk('local')->makeDirectory($directory);
            $pattern = Storage::disk('local')->path($directory.'/chunk-%03d.m4a');
            $result = Process::timeout(1200)->run([
                config('meetings.ffmpeg'), '-y', '-i', Storage::disk($original->disk)->path($original->path),
                '-vn', '-ac', '1', '-c:a', 'aac', '-b:a', '64k', '-f', 'segment',
                '-segment_time', (string) config('meetings.chunk_seconds'), '-reset_timestamps', '1', $pattern,
            ]);
            if ($result->failed()) {
                throw new RuntimeException('FFmpeg no pudo preparar los fragmentos de audio.');
            }

            foreach (glob(Storage::disk('local')->path($directory.'/chunk-*.m4a')) ?: [] as $index => $path) {
                $relative = $directory.'/'.basename($path);
                $info = $inspector->inspect($path);
                MeetingFile::updateOrCreate(
                    ['meeting_id' => $meeting->id, 'kind' => 'chunk', 'sequence' => $index],
                    [
                        'offset_ms' => $index * config('meetings.chunk_seconds') * 1000,
                        'disk' => 'local', 'path' => $relative, 'original_name' => basename($path),
                        'mime_type' => 'audio/mp4', 'size_bytes' => filesize($path),
                        'sha256' => hash_file('sha256', $path), 'md5' => hash_file('md5', $path),
                        'duration_ms' => $info['duration_ms'], 'uploaded_at' => now(), 'deleted_at' => null,
                    ],
                );
            }
        }

        $audit->log($meeting, 'processing.completed', null, [], 'prepare_audio');
        TranscribeMeetingJob::dispatch($meeting->id);
    }

    public function failed(Throwable $e): void
    {
        $meeting = Meeting::find($this->meetingId);
        $meeting?->update(['status' => MeetingStatus::Error, 'error_stage' => 'prepare_audio', 'error_message' => str($e->getMessage())->limit(500)]);
        if ($meeting) {
            app(AuditLogger::class)->log($meeting, 'processing.failed', null, ['message' => str($e->getMessage())->limit(500)->toString()], 'prepare_audio');
        }
    }
}
