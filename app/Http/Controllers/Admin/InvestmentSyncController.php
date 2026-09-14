<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncInvestmentDataJob;
use App\Models\InvestmentSyncRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvestmentSyncController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'universe' => ['required', 'in:governor,territory,ecosystem'],
            'project_limit' => ['nullable', 'integer', 'min:1', 'max:50000'],
        ]);
        if (InvestmentSyncRun::whereIn('status', ['pending', 'running'])->exists()) {
            return back()->with('error', 'Ya existe una actualización pendiente o en ejecución.');
        }
        $run = InvestmentSyncRun::create([
            'requested_by' => $request->user()->id,
            'universe' => $validated['universe'],
            'status' => 'pending',
            'datasets' => array_keys(config('investments.datasets')),
            'project_limit' => $validated['project_limit'] ?? (int) config('investments.default_limit'),
        ]);
        SyncInvestmentDataJob::dispatch($run->id);

        return back()->with('status', 'Actualización de inversión pública enviada a la cola.');
    }
}
