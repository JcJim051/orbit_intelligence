<?php

namespace App\Models;

use App\Enums\SpatialImportStatus;
use Database\Factories\SpatialImportContractFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpatialImportContract extends Model
{
    /** @use HasFactory<SpatialImportContractFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'field_mapping' => 'array',
            'status' => SpatialImportStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(SpatialImport::class, 'spatial_import_id');
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(SpatialDataset::class, 'spatial_dataset_id');
    }
}
