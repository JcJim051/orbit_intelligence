<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DatasetFieldType;
use App\Enums\DatasetFormVersionStatus;
use App\Enums\DatasetStatus;
use App\Enums\HistoricalDataPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpatialDatasetRequest;
use App\Http\Requests\UpdateSpatialDatasetRequest;
use App\Models\SpatialDataset;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SpatialDatasetController extends Controller
{
    public function index(): View
    {
        return view('admin.spatial-datasets.index', [
            'datasets' => SpatialDataset::query()
                ->with(['versions' => fn ($query) => $query->with(['fields', 'approver'])->orderByDesc('version')])
                ->orderBy('sector')
                ->orderBy('name')
                ->get(),
            'fieldTypes' => DatasetFieldType::cases(),
            'historicalPolicies' => HistoricalDataPolicy::cases(),
        ]);
    }

    public function store(StoreSpatialDatasetRequest $request, AuditLogger $audit): RedirectResponse
    {
        $spatialDataset = DB::transaction(function () use ($request): SpatialDataset {
            $spatialDataset = SpatialDataset::create([
                ...$request->validated(),
                'status' => DatasetStatus::Draft,
                'created_by' => $request->user()->id,
            ]);
            $spatialDataset->versions()->create([
                'version' => 1,
                'status' => DatasetFormVersionStatus::Draft,
                'created_by' => $request->user()->id,
            ]);

            return $spatialDataset;
        });

        $audit->log(null, 'spatial_dataset_created', $request->user(), ['spatial_dataset_id' => $spatialDataset->id], 'data_catalog', [], $spatialDataset->toArray());

        return back()->with('status', 'Conjunto de datos creado con la versión 1 del formulario en borrador.');
    }

    public function update(UpdateSpatialDatasetRequest $request, SpatialDataset $spatialDataset, AuditLogger $audit): RedirectResponse
    {
        $old = $spatialDataset->toArray();
        $spatialDataset->update($request->validated());
        $audit->log(null, 'spatial_dataset_updated', $request->user(), ['spatial_dataset_id' => $spatialDataset->id], 'data_catalog', $old, $spatialDataset->fresh()->toArray());

        return back()->with('status', 'Información general del conjunto de datos actualizada.');
    }
}
