<?php

namespace App\Models;

use App\Enums\GeoLayerAccessPolicy;
use Database\Factories\GeoLayerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GeoLayer extends Model
{
    /** @use HasFactory<GeoLayerFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'popup_fields' => 'array',
            'public_attribute_fields' => 'array',
            'style' => 'array',
            'filters' => 'array',
            'min_zoom' => 'integer',
            'max_zoom' => 'integer',
            'active' => 'boolean',
            'access_policy' => GeoLayerAccessPolicy::class,
            'access_policy_approved_at' => 'datetime',
        ];
    }

    public function accessPolicyApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'access_policy_approved_by');
    }

    public function isDownloadable(): bool
    {
        return $this->access_policy === GeoLayerAccessPolicy::Downloadable;
    }

    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(GeoViewer::class, 'geo_viewer_layers')
            ->withPivot(['label', 'group_name', 'sort_order', 'visible_by_default', 'show_in_legend', 'opacity'])
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
