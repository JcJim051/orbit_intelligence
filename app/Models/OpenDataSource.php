<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenDataSource extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'filters' => 'array',
            'popup_fields' => 'array',
            'style' => 'array',
            'last_checked_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(GeoLayer::class, 'geo_layer_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(OpenDataSnapshot::class);
    }
}
