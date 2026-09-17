<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DatasetFormVersionStatus;
use App\Enums\DatasetStatus;
use App\Http\Controllers\Controller;
use App\Models\SpatialDataset;
use App\Services\Postgis\MaterializeSpatialDataset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class SpatialDatasetMaterializationController extends Controller
{
    public function __invoke(SpatialDataset $spatialDataset, MaterializeSpatialDataset $materializer): RedirectResponse
    {
        Gate::authorize('approve-spatial-publication');
        abort_unless(
            $spatialDataset->status === DatasetStatus::Active
                && $spatialDataset->versions()->where('status', DatasetFormVersionStatus::Published->value)->exists(),
            409,
            'Publique primero el formulario de este conjunto.'
        );

        if (! $materializer->isAvailable()) {
            return back()->with('error', 'La preparación requiere que PostgreSQL esté activo.');
        }

        try {
            $materializer->materialize($spatialDataset);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No se pudo preparar la tabla para QGIS. Revise el error del conjunto y vuelva a intentarlo.')
                ->with('prepared_dataset', $spatialDataset->slug);
        }

        return back()->with('status', 'Tabla para QGIS preparada correctamente.')->with('prepared_dataset', $spatialDataset->slug);
    }
}
