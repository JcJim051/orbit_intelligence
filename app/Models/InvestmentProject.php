<?php

namespace App\Models;

use Database\Factories\InvestmentProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentProject extends Model
{
    /** @use HasFactory<InvestmentProjectFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_value' => 'decimal:2',
            'current_value' => 'decimal:2',
            'obligated_value' => 'decimal:2',
            'paid_value' => 'decimal:2',
            'physical_progress' => 'decimal:4',
            'financial_progress' => 'decimal:4',
            'is_governor_meta' => 'boolean',
            'is_territory_meta' => 'boolean',
            'is_ecosystem_meta' => 'boolean',
            'source_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    public function financials(): HasMany
    {
        return $this->hasMany(InvestmentFinancial::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(InvestmentLocation::class);
    }

    public function beneficiaryLocations(): HasMany
    {
        return $this->hasMany(InvestmentBeneficiary::class);
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(InvestmentProgressReport::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(InvestmentProduct::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(InvestmentContract::class);
    }

    public function policyFocuses(): HasMany
    {
        return $this->hasMany(InvestmentPolicyFocus::class);
    }

    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class, 'investment_project_meeting')
            ->withPivot(['id', 'agenda_reason', 'prepared_questions', 'added_by'])
            ->withTimestamps();
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ProjectDecision::class);
    }

    public function entityAssignments(): HasMany
    {
        return $this->hasMany(InvestmentEntityAssignment::class);
    }

    public function decentralizedEntities(): BelongsToMany
    {
        return $this->belongsToMany(InvestmentEntity::class, 'investment_entity_assignments')
            ->withPivot(['id', 'role', 'status', 'method', 'confidence', 'evidence', 'reviewed_by', 'reviewed_at'])
            ->withTimestamps();
    }

    public function scopeUniverse(Builder $query, string $universe): Builder
    {
        $column = match ($universe) {
            'governor' => 'is_governor_meta',
            'territory' => 'is_territory_meta',
            default => 'is_ecosystem_meta',
        };

        return $query->where($column, true);
    }
}
