<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDecision extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(InvestmentProject::class, 'investment_project_id');
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(MeetingSummary::class, 'meeting_summary_id');
    }
}
