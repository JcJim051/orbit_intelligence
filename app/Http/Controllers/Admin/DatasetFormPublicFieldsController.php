<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DatasetFormVersion;
use App\Models\SpatialDataset;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DatasetFormPublicFieldsController extends Controller
{
    public function __invoke(Request $request, SpatialDataset $spatialDataset, DatasetFormVersion $version, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($version->isDraft(), 409, 'Sólo se puede cambiar la visibilidad de una versión borrador.');

        $updated = $version->fields()->where('public_visible', false)->update(['public_visible' => true]);

        $audit->log(null, 'dataset_form_public_fields_enabled', $request->user(), [
            'spatial_dataset_id' => $spatialDataset->id,
            'form_version_id' => $version->id,
            'updated_fields' => $updated,
        ], 'data_catalog');

        return back()->with('status', "{$updated} campos seleccionados para mostrarse en la ficha pública al aprobar esta versión.");
    }
}
