<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentFinancial extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_value' => 'decimal:2', 'initial_value' => 'decimal:2',
            'current_value' => 'decimal:2', 'committed_value' => 'decimal:2',
            'obligated_value' => 'decimal:2', 'paid_value' => 'decimal:2',
            'source_updated_at' => 'datetime', 'raw_data' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(InvestmentProject::class, 'investment_project_id');
    }
}
