<?php

namespace App\Http\Controllers\Investment;

use App\Http\Controllers\Controller;
use App\Models\InvestmentFinancial;
use App\Models\InvestmentProject;
use App\Models\Meeting;
use App\Services\Investments\InvestmentMetricsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentProjectController extends Controller
{
    public function index(Request $request, InvestmentMetricsService $metrics): View
    {
        $startYear = (int) config('investments.government_period.start_year');
        $endYear = (int) config('investments.government_period.end_year');
        $filters = $request->validate([
            'universe' => ['nullable', 'in:governor,territory,ecosystem'], 'search' => ['nullable', 'string', 'max:200'],
            'year' => ['nullable', 'integer', "between:{$startYear},{$endYear}"], 'sector' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'], 'entity' => ['nullable', 'string', 'max:500'],
            'project_type' => ['nullable', 'string', 'max:100'], 'municipality' => ['nullable', 'string', 'max:10'],
            'funding_source' => ['nullable', 'string', 'max:500'],
            'period_mode' => ['nullable', 'in:execution,horizon'],
        ]);
        $filters['universe'] ??= 'governor';
        $filters['period_mode'] ??= 'execution';
        $projects = $metrics->query($filters)->with('locations:id,investment_project_id,municipality_code,municipality')
            ->orderByDesc('last_synced_at')->paginate(25)->withQueryString();

        return view('investments.projects.index', [
            'filters' => $filters, 'projects' => $projects,
            'sectors' => InvestmentProject::whereNotNull('sector')->distinct()->orderBy('sector')->pluck('sector'),
            'statuses' => InvestmentProject::whereNotNull('status')->distinct()->orderBy('status')->pluck('status'),
            'entities' => InvestmentProject::whereNotNull('responsible_entity')->distinct()->orderBy('responsible_entity')->limit(250)->pluck('responsible_entity'),
            'fundingSources' => InvestmentFinancial::whereNotNull('funding_source')->distinct()->orderBy('funding_source')->limit(250)->pluck('funding_source'),
            'municipalities' => config('investments.municipalities'),
        ]);
    }

    public function show(InvestmentProject $investmentProject, InvestmentMetricsService $metrics): View
    {
        $investmentProject->load([
            'locations', 'beneficiaryLocations', 'progressReports' => fn ($query) => $query->latest(), 'financials' => fn ($query) => $query->orderByDesc('fiscal_year'),
            'products', 'contracts', 'policyFocuses',
            'entityAssignments.entity', 'entityAssignments.reviewer',
            'meetings' => fn ($query) => $query->with('user')->latest('held_at'),
            'actionItems.summary.meeting', 'decisions.summary.meeting',
        ]);

        $meetingQuery = Meeting::query();
        $user = auth()->user();
        if (! $user->isAdmin()) {
            $meetingQuery->where(fn (Builder $query): Builder => $query->where('user_id', $user->id)->orWhere('reviewer_id', $user->id));
        }
        $meetings = $meetingQuery->latest('held_at')->limit(100)->get();

        return view('investments.projects.show', [
            'project' => $investmentProject,
            'alerts' => $metrics->alertsFor($investmentProject),
            'meetings' => $meetings,
        ]);
    }
}
