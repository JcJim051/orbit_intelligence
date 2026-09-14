<?php

namespace App\Jobs;

use App\Enums\DriveSyncStatus;
use App\Models\DriveExport;
use App\Services\AuditLogger;
use App\Services\GoogleDrive\GoogleDriveService;
use App\Services\MeetingExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PublishDriveExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 3600;

    public function __construct(public string $exportId)
    {
        $this->onQueue('exports');
    }

    public function handle(GoogleDriveService $drive, MeetingExportService $exports): void
    {
        $record = DriveExport::with(['meeting.transcript', 'summary.actionItems', 'connection'])->findOrFail($this->exportId);
        if ($record->status === 'synced') {
            return;
        }
        if (! $record->connection->active) {
            throw new \RuntimeException('El destino de Google Drive está inactivo.');
        }
        $record->increment('attempts');
        $artifact = $exports->materialize($record->meeting, $record->summary, $record->artifact_type);
        $folder = $exports->folder($record->meeting);
        $parentId = $drive->ensurePath($record->connection, $folder);
        $remoteName = $this->remoteName($artifact['name'], $record->summary->version);
        $remote = $drive->upload($record->connection, $artifact['path'], $remoteName, $artifact['mime'], $parentId, $record->id);
        $size = filesize($artifact['path']);
        if (isset($remote['size']) && (int) $remote['size'] !== $size) {
            throw new \RuntimeException('La verificación de tamaño en Google Drive no coincide.');
        }
        $record->update([
            'status' => 'synced', 'remote_path' => $folder.'/'.$remoteName,
            'remote_file_id' => $remote['id'], 'sha256' => hash_file('sha256', $artifact['path']),
            'md5' => hash_file('md5', $artifact['path']), 'size_bytes' => $size,
            'last_error' => null, 'synced_at' => now(),
        ]);
        app(AuditLogger::class)->log($record->meeting, 'drive_export.synced', null, [
            'connection' => $record->connection->label, 'artifact' => $record->artifact_type,
            'remote_file_id' => $remote['id'], 'sha256' => $record->sha256,
        ], 'drive_export');
        $this->refreshMeetingStatus($record->meeting);
    }

    public function failed(Throwable $e): void
    {
        $record = DriveExport::with('meeting')->find($this->exportId);
        if (! $record) {
            return;
        }
        $record->update(['status' => 'error', 'last_error' => str($e->getMessage())->limit(500)]);
        app(AuditLogger::class)->log($record->meeting, 'drive_export.failed', null, [
            'connection_id' => $record->drive_connection_id, 'artifact' => $record->artifact_type,
            'message' => str($e->getMessage())->limit(500)->toString(),
        ], 'drive_export');
        $this->refreshMeetingStatus($record->meeting);
    }

    private function refreshMeetingStatus($meeting): void
    {
        $total = $meeting->exports()->count();
        $synced = $meeting->exports()->where('status', 'synced')->count();
        $errors = $meeting->exports()->where('status', 'error')->count();
        $status = $total && $synced === $total ? DriveSyncStatus::Synced
            : ($synced > 0 ? DriveSyncStatus::Partial : ($errors === $total && $total > 0 ? DriveSyncStatus::Error : DriveSyncStatus::Pending));
        $meeting->update(['drive_sync_status' => $status]);
    }

    private function remoteName(string $name, int $version): string
    {
        if (str_starts_with($name, 'audio-original')) {
            return $name;
        }
        $suffix = '-v'.str_pad((string) $version, 3, '0', STR_PAD_LEFT);

        return pathinfo($name, PATHINFO_FILENAME).$suffix.'.'.pathinfo($name, PATHINFO_EXTENSION);
    }
}
