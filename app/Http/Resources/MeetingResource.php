<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'held_at' => $this->held_at?->toIso8601String(),
            'meeting_type' => $this->meeting_type,
            'participants' => $this->participants,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'drive_sync_status' => $this->drive_sync_status->value,
            'artifacts' => $this->artifacts,
            'error' => $this->when($this->error_message, ['stage' => $this->error_stage, 'message' => $this->error_message]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
