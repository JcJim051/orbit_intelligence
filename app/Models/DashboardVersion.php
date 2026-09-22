<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardVersion extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['config' => 'array', 'published_at' => 'datetime'];
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }
}
