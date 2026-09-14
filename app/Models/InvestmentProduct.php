<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentProduct extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4', 'indicator_target' => 'decimal:4',
            'indicator_progress' => 'decimal:4', 'product_value' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(InvestmentProject::class, 'investment_project_id');
    }
}
