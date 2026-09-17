<?php

namespace App\Models;

use App\Enums\SpatialImportStatus;
use Database\Factories\SpatialImportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpatialImport extends Model
{
    /** @use HasFactory<SpatialImportFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $hidden = ['database_password'];

    protected function casts(): array
    {
        return [
            'status' => SpatialImportStatus::class,
            'database_password' => 'encrypted',
            'expires_at' => 'datetime',
            'profile' => 'array',
            'profiled_at' => 'datetime',
            'field_mapping' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(SpatialDataset::class, 'spatial_dataset_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(SpatialImportContract::class);
    }
}
