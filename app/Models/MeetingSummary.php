<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingSummary extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'topics' => 'array',
            'decisions' => 'array',
            'risks' => 'array',
            'pending_questions' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function actionItems()
    {
        return $this->hasMany(ActionItem::class);
    }

    public function projectDecisions(): HasMany
    {
        return $this->hasMany(ProjectDecision::class);
    }
}
