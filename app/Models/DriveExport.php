<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class DriveExport extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function summary()
    {
        return $this->belongsTo(MeetingSummary::class, 'meeting_summary_id');
    }

    public function connection()
    {
        return $this->belongsTo(DriveConnection::class, 'drive_connection_id');
    }
}
