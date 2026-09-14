<?php

namespace App\Http\Controllers\Investment;

use App\Http\Controllers\Controller;
use App\Models\InvestmentEntity;
use App\Models\InvestmentEntityAssignment;
use App\Models\InvestmentProject;
use App\Services\Investments\InvestmentMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentEntityController extends Controller
{
    public function show(Request $request, InvestmentEntity $investmentEntity, InvestmentMetricsService $metrics): View
    {
        abort_unless($investmentEntity->active || $request->user()->isAdmin(), 404);

        $filters = $request->validate([
            'period_mode' => ['nullable', 'in:execution,horizon'],
            'search' => ['nullable', 'string', 'max:200'],
            'sector' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:10'],
        ]);
        $filters = [...$filters, 'universe' => 'governor', 'period_mode' => $filters['period_mode'] ?? 'execution', 'investment_entity' => $investmentEntity->slug];
        $projects = $metrics->query($filters)
            ->with('locations:id,investment_project_id,municipality_code,municipality')
            ->orderByDesc('last_synced_at')->paginate(25)->withQueryString();
        $projects->getCollection()->each(function (InvestmentProject $project) use ($metrics): void {
            $project->setAttribute('alerts_count', $metrics->alertsFor($project)->count());
        });
        $periodProjects = $metrics->query(['universe' => 'governor', 'period_mode' => $filters['period_mode']]);

        return view('investments.entities.show', [
            'entity' => $investmentEntity,
            'filters' => $filters,
            'projects' => $projects,
            'summary' => $metrics->summary($filters),
            'suggestedCount' => InvestmentEntityAssignment::query()
                ->where('investment_entity_id', $investmentEntity->id)->where('status', 'suggested')
                ->whereIn('investment_project_id', $periodProjects->select('investment_projects.id'))->distinct()->count('investment_project_id'),
            'sectors' => InvestmentProject::query()->whereNotNull('sector')->distinct()->orderBy('sector')->pluck('sector'),
            'statuses' => InvestmentProject::query()->whereNotNull('status')->distinct()->orderBy('status')->pluck('status'),
            'municipalities' => config('investments.municipalities'),
        ]);
    }
}
