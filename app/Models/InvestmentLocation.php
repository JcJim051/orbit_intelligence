<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentLocation extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_department_wide' => 'boolean', 'raw_data' => 'array'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(InvestmentProject::class, 'investment_project_id');
    }
}
