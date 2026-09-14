<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentSourceSnapshot extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['queried_at' => 'datetime', 'cutoff_at' => 'datetime', 'metadata' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(InvestmentSyncRun::class, 'investment_sync_run_id');
    }
}
