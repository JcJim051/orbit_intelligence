<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class InvestmentTerritorialResource extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'committed_value' => 'decimal:2', 'obligated_value' => 'decimal:2',
            'paid_value' => 'decimal:2', 'raw_data' => 'array',
        ];
    }
}
