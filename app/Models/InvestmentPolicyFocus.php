<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentPolicyFocus extends Model
{
    use HasUlids;

    protected $table = 'investment_policy_focuses';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'current_value' => 'decimal:2', 'committed_value' => 'decimal:2',
            'obligated_value' => 'decimal:2', 'paid_value' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(InvestmentProject::class, 'investment_project_id');
    }
}
