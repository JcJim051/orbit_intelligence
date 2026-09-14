<?php

namespace App\Models;

use App\Enums\ActionItemStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionItem extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => ActionItemStatus::class, 'due_date' => 'date'];
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(MeetingSummary::class, 'meeting_summary_id');
    }

    public function investmentProject(): BelongsTo
    {
        return $this->belongsTo(InvestmentProject::class);
    }
}
