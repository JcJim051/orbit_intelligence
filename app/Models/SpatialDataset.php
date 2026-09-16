<?php

namespace App\Models;

use App\Enums\DatasetStatus;
use Database\Factories\SpatialDatasetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpatialDataset extends Model
{
    /** @use HasFactory<SpatialDatasetFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DatasetStatus::class,
            'storage_srid' => 'integer',
            'materialized_form_version' => 'integer',
            'materialized_at' => 'datetime',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DatasetFormVersion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
