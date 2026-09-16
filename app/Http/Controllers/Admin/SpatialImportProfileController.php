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
        if (! in_array($spatialImport->status, [SpatialImportStatus::StagingReady, SpatialImportStatus::Profiled], true)) {
            return back()->with('error', 'Esta zona de carga ya no admite análisis ni tablas nuevas. Para cargar otra capa, cree una nueva zona temporal y use sus nuevas credenciales en QGIS.');
        }

        try {
            $profile = $profiler->profile($spatialImport);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No fue posible analizar el esquema temporal: '.$exception->getMessage());
        }

        $spatialImport->update([
            'profile' => $profile,
            'profiled_at' => now(),
            'status' => SpatialImportStatus::Profiled,
            'failure_message' => null,
        ]);
        $audit->log(null, 'spatial_import_profiled', $request->user(), [
            'spatial_import_id' => $spatialImport->id,
            'tables' => collect($profile['tables'])->pluck('name')->all(),
        ], 'spatial_import');

        return back()->with('status', count($profile['tables']).' tabla(s) detectada(s) en la zona temporal.');
    }
}
