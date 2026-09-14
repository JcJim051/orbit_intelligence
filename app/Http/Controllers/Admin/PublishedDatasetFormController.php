<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DatasetFormVersionStatus;
use App\Enums\DatasetStatus;
use App\Enums\SpatialImportStatus;
use App\Http\Controllers\Controller;
use App\Models\DatasetFormVersion;
use App\Models\SpatialDataset;
use App\Models\SpatialImport;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishedDatasetFormController extends Controller
{
    public function store(
        Request $request,
        SpatialDataset $spatialDataset,
        DatasetFormVersion $version,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('approve-spatial-publication');
        abort_unless($version->isDraft(), 409, 'Sólo se puede publicar una versión borrador.');
        $validated = $request->validate(['effective_from' => ['required', 'date']]);

        if (! $version->fields()->exists()) {
            return back()->with('error', 'Agregue por lo menos un campo antes de publicar el formulario.');
        }

        DB::transaction(function () use ($request, $spatialDataset, $version, $validated): void {
            $spatialDataset->versions()
                ->where('status', DatasetFormVersionStatus::Published->value)
                ->update(['status' => DatasetFormVersionStatus::Retired->value]);
            $version->update([
                'status' => DatasetFormVersionStatus::Published,
                'effective_from' => $validated['effective_from'],
                'published_at' => now(),
                'approved_by' => $request->user()->id,
            ]);
            $spatialDataset->update(['status' => DatasetStatus::Active]);
            SpatialImport::query()->whereBelongsTo($spatialDataset, 'dataset')->update([
                'status' => SpatialImportStatus::Approved,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        $audit->log(null, 'dataset_form_version_published', $request->user(), [
            'spatial_dataset_id' => $spatialDataset->id,
            'form_version_id' => $version->id,
            'version' => $version->version,
        ], 'data_catalog', [], $version->fresh()->toArray());

        return back()->with('status', "Versión {$version->version} publicada. Desde ahora es inmutable.");
    }
}
