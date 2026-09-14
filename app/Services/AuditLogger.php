<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\User;

class AuditLogger
{
    public function log(?Meeting $meeting, string $event, ?User $actor = null, array $metadata = [], ?string $stage = null, array $old = [], array $new = []): AuditLog
    {
        return AuditLog::create([
            'meeting_id' => $meeting?->id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'stage' => $stage,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'metadata' => $metadata ?: null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
