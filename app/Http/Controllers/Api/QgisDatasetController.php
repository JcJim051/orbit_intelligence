<?php

namespace App\Http\Controllers\Api;

use App\Enums\DatasetFormVersionStatus;
use App\Enums\DatasetStatus;
use App\Http\Controllers\Controller;
use App\Models\SpatialDataset;
use App\Services\Qgis\BuildDatasetManifest;
use Illuminate\Http\JsonResponse;

class QgisDatasetController extends Controller
{
    public function index(): JsonResponse
    {
        $datasets = SpatialDataset::query()
            ->where('status', DatasetStatus::Active->value)
            ->whereHas('versions', fn ($query) => $query->where('status', DatasetFormVersionStatus::Published->value))
            ->orderBy('sector')
            ->orderBy('name')
            ->get()
            ->map(fn (SpatialDataset $dataset): array => [
                'name' => $dataset->name,
                'slug' => $dataset->slug,
                'sector' => $dataset->sector,
                'geometry_type' => $dataset->geometry_type,
                'materialized' => $dataset->physical_table !== null,
                'form_url' => route('api.qgis.datasets.form', $dataset),
            ]);

        return response()->json(['data' => $datasets]);
    }

    public function show(SpatialDataset $spatialDataset, BuildDatasetManifest $manifest): JsonResponse
    {
        abort_unless($spatialDataset->status === DatasetStatus::Active, 404);
        abort_unless($spatialDataset->versions()->where('status', DatasetFormVersionStatus::Published->value)->exists(), 404);

        return response()->json(['data' => $manifest->build($spatialDataset)]);
    }
}
