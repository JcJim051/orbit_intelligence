<?php

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use LogicException;

class MeetingStateService
{
    private const ALLOWED = [
        'received' => ['pending_transcription', 'error'],
        'pending_transcription' => ['transcribing', 'error'],
        'transcribing' => ['pending_review', 'error'],
        'pending_review' => ['approved', 'error'],
        'approved' => ['pending_review'],
        'error' => ['pending_transcription', 'transcribing', 'pending_review'],
    ];

    public function transition(Meeting $meeting, MeetingStatus $to, ?string $stage = null): void
    {
        $from = $meeting->status->value;
        if ($meeting->status !== $to && ! in_array($to->value, self::ALLOWED[$from] ?? [], true)) {
            throw new LogicException("Transición de reunión no permitida: {$from} -> {$to->value}");
        }

        $meeting->forceFill([
            'status' => $to,
            'error_stage' => $to === MeetingStatus::Error ? $stage : null,
            'error_message' => $to === MeetingStatus::Error ? $meeting->error_message : null,
        ])->save();
    }
}
