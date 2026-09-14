<?php

namespace App\Http\Controllers\Investment;

use App\Http\Controllers\Controller;
use App\Models\InvestmentEntity;
use App\Models\InvestmentEntityAssignment;
use App\Models\InvestmentFinancial;
use App\Models\InvestmentLocation;
use App\Models\InvestmentSourceSnapshot;
use App\Models\InvestmentSyncRun;
use App\Services\Investments\InvestmentMetricsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentDashboardController extends Controller
{
    public function __invoke(Request $request, InvestmentMetricsService $metrics): View
    {
        $startYear = (int) config('investments.government_period.start_year');
        $endYear = (int) config('investments.government_period.end_year');
        $filters = $request->validate([
            'universe' => ['nullable', 'in:governor,territory,ecosystem'],
            'year' => ['nullable', 'integer', "between:{$startYear},{$endYear}"],
            'sector' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'period_mode' => ['nullable', 'in:execution,horizon'],
        ]);
        $filters['universe'] ??= 'governor';
        $filters['period_mode'] ??= 'execution';
        $query = $metrics->query($filters);

        $sectors = (clone $query)->selectRaw('sector, count(*) as projects, sum(total_value) as total_value')
            ->whereNotNull('sector')->groupBy('sector')->orderByDesc('projects')->limit(10)->get();
        $statuses = (clone $query)->selectRaw('status, count(*) as projects')
            ->whereNotNull('status')->groupBy('status')->orderByDesc('projects')->limit(10)->get();
        $periodProjectIds = (clone $query)->select('investment_projects.id');
        $suggestedByEntity = InvestmentEntityAssignment::query()
            ->whereIn('investment_project_id', $periodProjectIds)
            ->where('status', 'suggested')
            ->selectRaw('investment_entity_id, count(distinct investment_project_id) as projects')
            ->groupBy('investment_entity_id')->pluck('projects', 'investment_entity_id');
        $decentralizedEntities = InvestmentEntity::query()->where('active', true)->orderBy('sort_order')->get()
            ->map(function (InvestmentEntity $entity) use ($filters, $metrics, $suggestedByEntity): InvestmentEntity {
                $entity->setAttribute('metrics', $metrics->summary([...$filters, 'investment_entity' => $entity->slug]));
                $entity->setAttribute('suggested_projects', (int) ($suggestedByEntity[$entity->id] ?? 0));

                return $entity;
            });
        $projectIds = (clone $query)->select('investment_projects.id');
        $fundingSources = InvestmentFinancial::query()
            ->whereIn('investment_project_id', $projectIds)
            ->whereNotNull('funding_source')
            ->selectRaw('funding_source, count(distinct investment_project_id) as projects')
            ->groupBy('funding_source')->orderByDesc('projects')->limit(10)->get();
        $years = InvestmentFinancial::query()
            ->whereIn('investment_project_id', (clone $query)->select('investment_projects.id'))
            ->whereBetween('fiscal_year', [(int) config('investments.government_period.start_year'), (int) config('investments.government_period.end_year')])
            ->whereNotNull('fiscal_year')
            ->selectRaw('fiscal_year, count(distinct investment_project_id) as projects')
            ->groupBy('fiscal_year')->orderByDesc('fiscal_year')->limit(10)->get();
        $municipalityRows = InvestmentLocation::query()
            ->where('department_code', '50')
            ->whereIn('investment_project_id', (clone $query)->select('investment_projects.id'))
            ->selectRaw('municipality_code, municipality, count(distinct investment_project_id) as projects')
            ->groupBy('municipality_code', 'municipality')->get()->keyBy('municipality_code');
        $municipalities = collect(config('investments.municipalities'))->map(function (string $name, string $code) use ($municipalityRows): array {
            return ['code' => $code, 'name' => $name, 'projects' => (int) ($municipalityRows->get($code)?->projects ?? 0)];
        })->values();

        return view('investments.dashboard', [
            'filters' => $filters,
            'summary' => $metrics->summary($filters),
            'sectors' => $sectors,
            'statuses' => $statuses,
            'decentralizedEntities' => $decentralizedEntities,
            'fundingSources' => $fundingSources,
            'years' => $years,
            'municipalities' => $municipalities,
            'latestSnapshot' => InvestmentSourceSnapshot::latest('queried_at')->first(),
            'latestRun' => InvestmentSyncRun::latest()->first(),
            'qualityCount' => (clone $query)->where(fn (Builder $builder): Builder => $builder->whereNull('responsible_entity')->orWhereNull('current_value')->orWhereNull('physical_progress'))->count(),
        ]);
    }
}
