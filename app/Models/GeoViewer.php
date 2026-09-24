<?php

namespace App\Models;

use App\Enums\GeoViewerStatus;
use Database\Factories\GeoViewerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GeoViewer extends Model
{
    /** @use HasFactory<GeoViewerFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'center_latitude' => 'float',
            'center_longitude' => 'float',
            'initial_zoom' => 'integer',
            'status' => GeoViewerStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function layers(): BelongsToMany
    {
        return $this->belongsToMany(GeoLayer::class, 'geo_viewer_layers')
            ->withPivot(['label', 'group_name', 'sort_order', 'visible_by_default', 'show_in_legend', 'opacity'])
            ->withTimestamps();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'geo_viewer_collaborators')
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function canBeEditedBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->isPublished() || $this->status === GeoViewerStatus::Review) {
            return false;
        }

        return $this->owner_id === $user->id
            || $this->collaborators()->whereKey($user->id)->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isPublished(): bool
    {
        return $this->status === GeoViewerStatus::Published;
    }
}
