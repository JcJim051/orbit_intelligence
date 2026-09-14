<?php

namespace App\Models;

use App\Enums\DatasetFormVersionStatus;
use Database\Factories\DatasetFormVersionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatasetFormVersion extends Model
{
    /** @use HasFactory<DatasetFormVersionFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => DatasetFormVersionStatus::class,
            'effective_from' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(SpatialDataset::class, 'spatial_dataset_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DatasetFormField::class)->orderBy('sort_order')->orderBy('label');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === DatasetFormVersionStatus::Draft;
    }
}
