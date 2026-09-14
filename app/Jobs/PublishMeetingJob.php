<?php

namespace App\Jobs;

use App\Enums\DriveSyncStatus;
use App\Models\DriveConnection;
use App\Models\DriveExport;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishMeetingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $meetingId)
    {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $meeting = Meeting::with(['transcript', 'summaries.actionItems'])->findOrFail($this->meetingId);
        $summary = $meeting->currentSummary();
        if (! $summary || $summary->status !== 'approved') {
            return;
        }

        $types = array_values(array_unique([...$meeting->artifacts, 'manifest']));
        $connections = DriveConnection::query()->whereIn('id', $meeting->drive_connection_ids ?? [])->get();
        foreach ($connections as $connection) {
            foreach ($types as $type) {
                if ($type === 'audio' && DriveExport::query()->where('meeting_id', $meeting->id)->where('drive_connection_id', $connection->id)->where('artifact_type', 'audio')->where('status', 'synced')->exists()) {
                    continue;
                }
                $export = DriveExport::firstOrCreate([
                    'meeting_id' => $meeting->id,
                    'meeting_summary_id' => $summary->id,
                    'drive_connection_id' => $connection->id,
                    'artifact_type' => $type,
                ], ['status' => 'pending']);
                if (! $connection->active) {
                    $export->update(['status' => 'error', 'last_error' => 'El destino de Google Drive está inactivo.']);
                } elseif ($export->status !== 'synced') {
                    PublishDriveExportJob::dispatch($export->id);
                }
            }
        }

        $total = $meeting->exports()->count();
        $synced = $meeting->exports()->where('status', 'synced')->count();
        $errors = $meeting->exports()->where('status', 'error')->count();
        $meeting->update(['drive_sync_status' => $total === 0
            ? DriveSyncStatus::NotRequested
            : ($synced === $total ? DriveSyncStatus::Synced
                : ($synced > 0 ? DriveSyncStatus::Partial : ($errors === $total ? DriveSyncStatus::Error : DriveSyncStatus::Pending)))]);
    }
}
