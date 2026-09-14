<?php

namespace App\Services;

use App\Contracts\AudioInspector;
use App\Enums\MeetingStatus;
use App\Jobs\PrepareMeetingAudioJob;
use App\Models\DriveConnection;
use App\Models\Meeting;
use App\Models\MeetingFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MeetingIngestionService
{
    public function __construct(private AudioInspector $inspector, private AuditLogger $audit) {}

    public function ingest(User $user, UploadedFile $audio, array $data, string $idempotencyKey): Meeting
    {
        if ($existing = Meeting::whereBelongsTo($user)->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        try {
            $inspection = $this->inspector->inspect($audio->getRealPath());
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['audio' => $e->getMessage()]);
        }

        if ($inspection['duration_ms'] > config('meetings.max_duration_seconds') * 1000) {
            throw ValidationException::withMessages(['audio' => 'La grabación supera el límite de 2 horas.']);
        }

        $sha256 = hash_file('sha256', $audio->getRealPath());
        $md5 = hash_file('md5', $audio->getRealPath());
        $meetingId = (string) Str::ulid();
        $extension = strtolower($audio->extension() ?: 'bin');
        $path = "meetings/{$user->id}/{$meetingId}/original.{$extension}";
        if (! Storage::disk('local')->putFileAs(dirname($path), $audio, basename($path))) {
            throw ValidationException::withMessages(['audio' => 'No fue posible almacenar el archivo de forma segura.']);
        }

        try {
            $meeting = DB::transaction(function () use ($user, $data, $idempotencyKey, $meetingId, $path, $audio, $inspection, $sha256, $md5) {
                $connectionIds = $data['drive_connection_ids'] ?? DriveConnection::query()->where('active', true)->pluck('id')->all();
                $meeting = Meeting::create([
                    'id' => $meetingId,
                    'user_id' => $user->id,
                    'reviewer_id' => $data['reviewer_id'] ?? null,
                    'title' => $data['title'],
                    'held_at' => $data['held_at'],
                    'meeting_type' => $data['meeting_type'],
                    'participants' => $data['participants'] ?? [],
                    'recording_consent_confirmed' => true,
                    'consent_confirmed_at' => now(),
                    'artifacts' => $data['artifacts'],
                    'drive_connection_ids' => $connectionIds,
                    'idempotency_key' => $idempotencyKey,
                    'status' => MeetingStatus::Received,
                ]);

                MeetingFile::create([
                    'meeting_id' => $meeting->id,
                    'kind' => 'original',
                    'sequence' => 0,
                    'offset_ms' => 0,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $audio->getClientOriginalName(),
                    'mime_type' => $inspection['mime_type'],
                    'size_bytes' => $audio->getSize(),
                    'sha256' => $sha256,
                    'md5' => $md5,
                    'duration_ms' => $inspection['duration_ms'],
                    'uploaded_at' => now(),
                ]);

                $this->audit->log($meeting, 'meeting.uploaded', $user, [
                    'sha256' => $sha256,
                    'duration_ms' => $inspection['duration_ms'],
                    'original_name' => $audio->getClientOriginalName(),
                ]);

                return $meeting;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        $meeting->update(['status' => MeetingStatus::PendingTranscription]);
        Bus::chain([new PrepareMeetingAudioJob($meeting->id)])->dispatch();

        return $meeting->fresh(['files']);
    }
}
