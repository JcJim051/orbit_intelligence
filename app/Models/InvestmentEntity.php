<?php

namespace App\Models;

use Database\Factories\InvestmentEntityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentEntity extends Model
{
    /** @use HasFactory<InvestmentEntityFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aliases' => 'array', 'active' => 'boolean'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(InvestmentEntityAssignment::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(InvestmentProject::class, 'investment_entity_assignments')
            ->withPivot(['id', 'role', 'status', 'method', 'confidence', 'evidence', 'reviewed_by', 'reviewed_at'])
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
