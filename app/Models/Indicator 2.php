<?php

namespace App\Models;

use App\Enums\IndicatorStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Indicator extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => IndicatorStatus::class,
            'public_metadata' => 'array',
            'published_version' => 'integer',
            'technical_sheet_size' => 'integer',
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

    public function versions(): HasMany
    {
        return $this->hasMany(IndicatorVersion::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isPublished(): bool
    {
        return $this->status === IndicatorStatus::Published && $this->published_at !== null;
    }

    public function hasTechnicalSheet(): bool
    {
        return filled($this->technical_sheet_path)
            && Storage::disk($this->technical_sheet_disk ?: 'local')->exists($this->technical_sheet_path);
    }

    public function canEdit(User $user): bool
    {
        return $user->isAdmin() || $this->owner_id === $user->id;
    }
}
