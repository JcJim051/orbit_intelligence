<?php

namespace App\Services\Investments;

use App\Models\InvestmentFinancial;
use App\Models\InvestmentProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvestmentMetricsService
{
    /** @param array<string, mixed> $filters */
    public function query(array $filters): Builder
    {
        $universe = in_array($filters['universe'] ?? null, ['governor', 'territory', 'ecosystem'], true)
            ? $filters['universe'] : 'governor';
        $periodMode = in_array($filters['period_mode'] ?? null, ['execution', 'horizon'], true)
            ? $filters['period_mode'] : 'execution';
        $startYear = (int) config('investments.government_period.start_year');
        $endYear = (int) config('investments.government_period.end_year');

        $financialPeriodFilter = function (Builder $financial) use ($filters, $startYear, $endYear): Builder {
            return $financial->where('source_dataset_id', 'v4ap-cvae')
                ->whereBetween('fiscal_year', [$startYear, $endYear])
                ->when($filters['year'] ?? null, fn (Builder $query, int|string $year): Builder => $query->where('fiscal_year', (int) $year))
                ->when($filters['funding_source'] ?? null, fn (Builder $query, string $source): Builder => $query->where('funding_source', $source));
        };

        return InvestmentProject::query()->universe($universe)
            ->when($periodMode === 'execution', fn (Builder $query): Builder => $query->whereHas('financials', $financialPeriodFilter))
            ->when($periodMode === 'horizon', fn (Builder $query): Builder => $query
                ->whereNotNull('horizon_start_year')
                ->whereNotNull('horizon_end_year')
                ->where('horizon_start_year', '<=', $endYear)
                ->where('horizon_end_year', '>=', $startYear))
            ->when($periodMode === 'horizon' && (($filters['year'] ?? null) || ($filters['funding_source'] ?? null)), fn (Builder $query): Builder => $query->whereHas('financials', $financialPeriodFilter))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('bpin', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('responsible_entity', 'like', "%{$search}%");
                });
            })
            ->when($filters['sector'] ?? null, fn (Builder $query, string $sector): Builder => $query->where('sector', $sector))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['entity'] ?? null, fn (Builder $query, string $entity): Builder => $query->where('responsible_entity', $entity))
            ->when($filters['project_type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('project_type', $type))
            ->when($filters['municipality'] ?? null, fn (Builder $query, string $code): Builder => $query->whereHas('locations', fn (Builder $location): Builder => $location->where('municipality_code', $code)))
            ->when($filters['investment_entity'] ?? null, fn (Builder $query, string $slug): Builder => $query->whereHas('decentralizedEntities', fn (Builder $entity): Builder => $entity
                ->where('investment_entities.slug', $slug)
                ->where('investment_entity_assignments.status', 'confirmed')
                ->where('investment_entity_assignments.role', 'primary')));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|float|null>
     */
    public function summary(array $filters): array
    {
        $projects = $this->query($filters);
        $projectSubquery = (clone $projects)->select('investment_projects.id');
        $financials = InvestmentFinancial::query()
            ->where('source_dataset_id', 'v4ap-cvae')
            ->whereIn('investment_project_id', $projectSubquery)
            ->whereBetween('fiscal_year', [
                (int) config('investments.government_period.start_year'),
                (int) config('investments.government_period.end_year'),
            ])
            ->when($filters['year'] ?? null, fn (Builder $query, int|string $year): Builder => $query->where('fiscal_year', (int) $year))
            ->when($filters['funding_source'] ?? null, fn (Builder $query, string $source): Builder => $query->where('funding_source', $source));

        $financial = $financials->selectRaw('sum(current_value) as current_value, sum(committed_value) as committed_value, sum(obligated_value) as obligated_value, sum(paid_value) as paid_value')->first();
        $current = $financial?->current_value !== null ? (float) $financial->current_value : null;
        $paid = $financial?->paid_value !== null ? (float) $financial->paid_value : null;

        return [
            'projects' => (clone $projects)->count(),
            'total_value' => $this->nullableSum(clone $projects, 'total_value'),
            'current_value' => $current,
            'committed_value' => $financial?->committed_value !== null ? (float) $financial->committed_value : null,
            'obligated_value' => $financial?->obligated_value !== null ? (float) $financial->obligated_value : null,
            'paid_value' => $paid,
            'financial_execution_percent' => $this->weightedExecutionPercent($paid, $current),
            'physical_progress_percent' => (clone $projects)->whereNotNull('physical_progress')->avg('physical_progress'),
        ];
    }

    public function weightedExecutionPercent(?float $executed, ?float $approvedOrCurrent): ?float
    {
        if ($executed === null || $approvedOrCurrent === null || $approvedOrCurrent <= 0) {
            return null;
        }

        return round(($executed / $approvedOrCurrent) * 100, 2);
    }

    /** @return Collection<int, array{type: string, label: string, severity: string}> */
    public function alertsFor(InvestmentProject $project): Collection
    {
        $alerts = collect();
        $current = $project->current_value !== null ? (float) $project->current_value : null;
        $paid = $project->paid_value !== null ? (float) $project->paid_value : null;
        $execution = $current !== null && $current > 0 && $paid !== null ? ($paid / $current) * 100 : null;

        if ($execution !== null && $execution < (float) config('investments.low_execution_percent')) {
            $alerts->push(['type' => 'low_execution', 'label' => 'Ejecución pagada/vigente por debajo del umbral interno', 'severity' => 'warning']);
        }
        if ($project->physical_progress !== null && $project->financial_progress !== null && abs((float) $project->physical_progress - (float) $project->financial_progress) >= (float) config('investments.progress_gap_points')) {
            $alerts->push(['type' => 'progress_gap', 'label' => 'Diferencia relevante entre avances físico y financiero', 'severity' => 'warning']);
        }
        if ($project->last_synced_at === null || $project->last_synced_at->lt(now()->subDays((int) config('investments.stale_after_days')))) {
            $alerts->push(['type' => 'stale', 'label' => 'Sin actualización reciente', 'severity' => 'warning']);
        }
        if (preg_match('/(suspend|cancel|cr[ií]tic|inviable)/iu', (string) $project->status.' '.(string) $project->substatus)) {
            $alerts->push(['type' => 'critical_status', 'label' => 'Estado que requiere atención', 'severity' => 'danger']);
        }
        preg_match_all('/(?:19|20|21)\d{2}/', (string) $project->horizon, $years);
        $endingYear = collect($years[0] ?? [])->map(fn (string $year): int => (int) $year)->max();
        if ($endingYear !== null) {
            $endingAt = now()->setYear($endingYear)->endOfYear();
            if ($endingAt->isFuture() && now()->diffInDays($endingAt) <= (int) config('investments.ending_within_days')) {
                $alerts->push(['type' => 'ending_soon', 'label' => "Horizonte reportado próximo a finalizar ({$endingYear})", 'severity' => 'warning']);
            }
        }
        if ($project->name && InvestmentProject::query()
            ->whereKeyNot($project->getKey())
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($project->name)])
            ->exists()) {
            $alerts->push(['type' => 'possible_duplicate', 'label' => 'Posible duplicidad: otro BPIN publica el mismo nombre', 'severity' => 'quality']);
        }
        foreach (['responsible_entity' => 'entidad responsable', 'current_value' => 'información financiera', 'physical_progress' => 'avance físico'] as $field => $label) {
            if ($project->{$field} === null || $project->{$field} === '') {
                $alerts->push(['type' => 'missing_'.$field, 'label' => 'Dato no reportado: '.$label, 'severity' => 'quality']);
            }
        }
        if (! $project->locations()->exists()) {
            $alerts->push(['type' => 'missing_location', 'label' => 'Registro sin localización asociada', 'severity' => 'quality']);
        }

        return $alerts;
    }

    private function nullableSum(Builder $query, string $column): ?float
    {
        if (! (clone $query)->whereNotNull($column)->exists()) {
            return null;
        }

        return (float) $query->sum($column);
    }
}
