<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabularDataSourceVersion extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fields' => 'array', 'records' => 'array', 'validation_summary' => 'array'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(TabularDataSource::class, 'tabular_data_source_id');
    }
}
