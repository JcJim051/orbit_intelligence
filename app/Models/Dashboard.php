<?php

namespace App\Models;

use App\Enums\DashboardStatus;
use Database\Factories\DashboardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dashboard extends Model
{
    /** @use HasFactory<DashboardFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DashboardStatus::class,
            'draft_config' => 'array',
            'published_version' => 'integer',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dashboard_collaborators')
            ->withPivot('permission')->withTimestamps();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DashboardVersion::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isPublished(): bool
    {
        return $this->published_version !== null;
    }

    public function canEdit(User $user): bool
    {
        return $user->isAdmin() || $this->owner_id === $user->id
            || $this->collaborators()->whereKey($user->id)->exists();
    }
}
