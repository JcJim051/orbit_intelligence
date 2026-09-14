<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestmentEntity;
use App\Models\InvestmentEntityAssignment;
use App\Models\InvestmentProject;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentEntityController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', 'in:suggested,confirmed,rejected,unclassified']])['status'] ?? 'suggested';
        $assignments = $status === 'unclassified' ? null : InvestmentEntityAssignment::query()
            ->with(['project', 'entity', 'reviewer'])->where('status', $status)
            ->latest()->paginate(30)->withQueryString();
        $unclassifiedProjects = $status === 'unclassified' ? InvestmentProject::query()
            ->where('is_governor_meta', true)
            ->whereDoesntHave('entityAssignments')
            ->latest('last_synced_at')->paginate(30)->withQueryString() : null;

        return view('admin.investment-entities.index', [
            'entities' => InvestmentEntity::query()->withCount([
                'assignments as confirmed_count' => fn ($query) => $query->where('status', 'confirmed'),
                'assignments as suggested_count' => fn ($query) => $query->where('status', 'suggested'),
            ])->orderBy('sort_order')->get(),
            'assignments' => $assignments,
            'unclassifiedProjects' => $unclassifiedProjects,
            'status' => $status,
        ]);
    }

    public function update(Request $request, InvestmentEntity $investmentEntity, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'acronym' => ['nullable', 'string', 'max:30'],
            'aliases' => ['required', 'string', 'max:5000'],
            'source_url' => ['required', 'url', 'max:2000'],
            'sort_order' => ['required', 'integer', 'between:0,1000'],
            'active' => ['nullable', 'boolean'],
        ]);
        $old = $investmentEntity->toArray();
        $investmentEntity->update([
            ...$validated,
            'aliases' => collect(preg_split('/\r\n|\r|\n/', $validated['aliases']))->map->trim()->filter()->unique()->values()->all(),
            'active' => $request->boolean('active'),
        ]);
        $audit->log(null, 'investment_entity_updated', $request->user(), ['entity_id' => $investmentEntity->id], 'investment', $old, $investmentEntity->fresh()->toArray());

        return back()->with('status', 'Entidad descentralizada actualizada.');
    }
}
