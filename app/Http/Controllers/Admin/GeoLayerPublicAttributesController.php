<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DatasetFormVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\GeoLayer;
use App\Models\SpatialDataset;
use App\Services\AuditLogger;
use App\Services\Postgis\MaterializeSpatialDataset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Throwable;

class GeoLayerPublicAttributesController extends Controller
{
    public function __invoke(
        Request $request,
        GeoLayer $geoLayer,
        MaterializeSpatialDataset $materializer,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('approve-spatial-publication');

        $dataset = SpatialDataset::query()->where('slug', $geoLayer->slug)->firstOrFail();
        abort_unless(
            $geoLayer->source_type === 'geojson'
                && $geoLayer->source_url === '/api/public/geodata/'.$dataset->slug
                && $dataset->physical_table,
            404
        );
        $version = $dataset->versions()
            ->where('status', DatasetFormVersionStatus::Published->value)
            ->with('fields')
            ->latest('version')
            ->firstOrFail();
        abort_unless($dataset->materialized_form_version >= $version->version, 409, 'Prepare la capa publicada antes de configurar sus atributos.');
        $availableFields = $version->fields->pluck('key')->all();
        $validated = $request->validate([
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['string', Rule::in($availableFields)],
        ], [
            'fields.array' => 'La selección de atributos no es válida.',
            'fields.*.in' => 'Solo puede mostrar atributos del formulario publicado.',
        ]);
        $selected = array_values(array_unique($validated['fields'] ?? []));

        if (! $materializer->isAvailable()) {
            return back()->with('error', 'La configuración de atributos requiere PostgreSQL activo.');
        }

        try {
            $materializer->updatePublicAttributes($dataset, $geoLayer, $selected);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No se pudieron actualizar los atributos públicos. No se cambió la selección anterior.');
        }

        $audit->log(null, 'geo_layer_public_attributes_updated', $request->user(), [
            'geo_layer_id' => $geoLayer->id,
            'spatial_dataset_id' => $dataset->id,
            'fields' => $selected,
        ], 'geovisors');

        return back()->with('status', 'Atributos públicos actualizados sin crear otra versión. Recargue el visor para ver el cambio.');
    }
}
