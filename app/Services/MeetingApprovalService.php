<?php

namespace App\Services;

use App\Enums\ActionItemStatus;
use App\Enums\DriveSyncStatus;
use App\Enums\MeetingStatus;
use App\Jobs\PublishMeetingJob;
use App\Models\Meeting;
use App\Models\MeetingSummary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingApprovalService
{
    public function __construct(private AuditLogger $audit) {}

    public function approve(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor) {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
            if ($meeting->status !== MeetingStatus::PendingReview) {
                throw ValidationException::withMessages(['meeting' => 'La reunión no está pendiente de revisión.']);
            }
            $summary = $meeting->currentSummary();
            if (! $summary || $summary->actionItems()->where('status', ActionItemStatus::Draft->value)->exists()) {
                throw ValidationException::withMessages(['actions' => 'Aprueba o descarta todos los compromisos antes de aprobar el acta.']);
            }

            $summary->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->id]);
            $meeting->update([
                'status' => MeetingStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->id,
                'drive_sync_status' => count($meeting->drive_connection_ids ?? []) ? DriveSyncStatus::Pending : DriveSyncStatus::NotRequested,
            ]);
            $this->audit->log($meeting, 'meeting.approved', $actor, ['version' => $summary->version]);
        });

        PublishMeetingJob::dispatch($meeting->id);
    }

    public function reopen(Meeting $meeting, User $actor): MeetingSummary
    {
        return DB::transaction(function () use ($meeting, $actor) {
            $current = $meeting->currentSummary();
            if ($meeting->status !== MeetingStatus::Approved || ! $current) {
                throw ValidationException::withMessages(['meeting' => 'Solo se puede reabrir un acta aprobada.']);
            }
            $clone = $current->replicate(['approved_at', 'approved_by']);
            $clone->id = null;
            $clone->version = $current->version + 1;
            $clone->status = 'draft';
            $clone->save();
            foreach ($current->actionItems as $item) {
                $new = $item->replicate();
                $new->id = null;
                $new->meeting_summary_id = $clone->id;
                $new->status = ActionItemStatus::Draft;
                $new->save();
            }
            $meeting->update(['status' => MeetingStatus::PendingReview, 'approved_at' => null, 'approved_by' => null]);
            $this->audit->log($meeting, 'meeting.reopened', $actor, ['from_version' => $current->version, 'to_version' => $clone->version]);

            return $clone;
        });
    }
}
