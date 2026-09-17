<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SpatialImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileSpatialImportRequest;
use App\Models\SpatialImport;
use App\Services\AuditLogger;
use App\Services\Postgis\SpatialImportProfiler;
use Illuminate\Http\RedirectResponse;
use Throwable;

class SpatialImportProfileController extends Controller
{
    public function __invoke(
        ProfileSpatialImportRequest $request,
        SpatialImport $spatialImport,
        SpatialImportProfiler $profiler,
        AuditLogger $audit,
    ): RedirectResponse {
        if (! in_array($spatialImport->status, [SpatialImportStatus::StagingReady, SpatialImportStatus::Profiled, SpatialImportStatus::ContractDraft, SpatialImportStatus::Approved], true)) {
            return back()->with('error', 'Esta zona de carga está cerrada y no admite análisis.');
        }

        try {
            $profile = $profiler->profile($spatialImport);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No fue posible analizar el esquema temporal: '.$exception->getMessage());
        }

        $previousTables = collect(data_get($spatialImport->profile, 'tables', []))->pluck('name');
        $newTables = collect($profile['tables'])->pluck('name')->diff($previousTables)->values();
        $spatialImport->update([
            'profile' => $profile,
            'profiled_at' => now(),
            'status' => in_array($spatialImport->status, [SpatialImportStatus::StagingReady, SpatialImportStatus::Profiled], true)
                ? SpatialImportStatus::Profiled
                : $spatialImport->status,
            'failure_message' => null,
        ]);
        $audit->log(null, 'spatial_import_profiled', $request->user(), [
            'spatial_import_id' => $spatialImport->id,
            'tables' => collect($profile['tables'])->pluck('name')->all(),
        ], 'spatial_import');

        return back()->with('status', count($profile['tables']).' tabla(s) detectada(s); '.$newTables->count().' nueva(s). La aprobación existente no cambió.');
    }
}
