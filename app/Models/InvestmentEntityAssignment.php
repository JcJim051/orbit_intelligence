<?php

namespace App\Models;

use Database\Factories\InvestmentEntityAssignmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentEntityAssignment extends Model
{
    /** @use HasFactory<InvestmentEntityAssignmentFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['confidence' => 'decimal:4', 'evidence' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(InvestmentProject::class, 'investment_project_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(InvestmentEntity::class, 'investment_entity_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
