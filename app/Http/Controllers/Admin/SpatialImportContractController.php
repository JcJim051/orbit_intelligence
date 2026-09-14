<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SpatialImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpatialImportContractRequest;
use App\Models\SpatialImport;
use App\Services\AuditLogger;
use App\Services\Postgis\CreateSpatialContractFromImport;
use Illuminate\Http\RedirectResponse;
use Throwable;

class SpatialImportContractController extends Controller
{
    public function __invoke(
        StoreSpatialImportContractRequest $request,
        SpatialImport $spatialImport,
        CreateSpatialContractFromImport $creator,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($spatialImport->status === SpatialImportStatus::Profiled, 409, 'Primero debe perfilar una importación abierta.');

        try {
            $dataset = $creator->create($spatialImport, $request->user(), $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No fue posible crear el contrato: '.$exception->getMessage());
        }

        $audit->log(null, 'spatial_import_contract_drafted', $request->user(), [
            'spatial_import_id' => $spatialImport->id,
            'spatial_dataset_id' => $dataset->id,
            'source_table' => $spatialImport->fresh()->selected_table,
        ], 'spatial_import');

        return redirect()->route('admin.spatial-datasets.index')->with('status', 'Contrato v1 creado en borrador. Revise los campos y publíquelo cuando esté listo.');
    }
}
