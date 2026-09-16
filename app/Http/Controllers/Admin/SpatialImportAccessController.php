<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SpatialImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RenewSpatialImportAccessRequest;
use App\Models\SpatialImport;
use App\Services\AuditLogger;
use App\Services\Postgis\ProvisionSpatialImportStaging;
use Illuminate\Http\RedirectResponse;
use Throwable;

class SpatialImportAccessController extends Controller
{
    public function __invoke(
        RenewSpatialImportAccessRequest $request,
        SpatialImport $spatialImport,
        ProvisionSpatialImportStaging $staging,
        AuditLogger $audit,
    ): RedirectResponse {
        if (! in_array($spatialImport->status, [SpatialImportStatus::StagingReady, SpatialImportStatus::Profiled, SpatialImportStatus::ContractDraft, SpatialImportStatus::Approved], true)) {
            return back()->with('error', 'Esta importación no admite renovación.');
        }

        $expiresAt = now()->addHours((int) $request->validated('valid_for_hours'));

        try {
            $staging->renewAccess($spatialImport, $expiresAt);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No fue posible renovar el acceso QGIS. Verifique la conexión PostgreSQL y el usuario temporal.');
        }

        $previousExpiration = $spatialImport->expires_at;
        $spatialImport->update(['expires_at' => $expiresAt]);
        $audit->log(null, 'spatial_import_access_renewed', $request->user(), [
            'spatial_import_id' => $spatialImport->id,
            'schema' => $spatialImport->staging_schema,
            'previous_expiration' => $previousExpiration->toIso8601String(),
            'new_expiration' => $expiresAt->toIso8601String(),
        ], 'spatial_import');

        return back()->with('status', 'Acceso QGIS renovado hasta el '.$expiresAt->copy()->timezone('America/Bogota')->format('d/m/Y H:i').' (hora Colombia). Use en QGIS el mismo usuario, contraseña y esquema.');
    }
}
