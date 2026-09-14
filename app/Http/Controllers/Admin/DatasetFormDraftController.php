<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DatasetFormVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\DatasetFormVersion;
use App\Models\SpatialDataset;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DatasetFormDraftController extends Controller
{
    public function store(Request $request, SpatialDataset $spatialDataset, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($spatialDataset->versions()->where('status', DatasetFormVersionStatus::Draft->value)->exists()) {
            return back()->with('error', 'Este conjunto de datos ya tiene una versión borrador.');
        }

        $sourceVersion = $spatialDataset->versions()
            ->with('fields')
            ->orderByDesc('version')
            ->firstOrFail();

        $draft = DB::transaction(function () use ($request, $spatialDataset, $sourceVersion): DatasetFormVersion {
            $nextVersion = $spatialDataset->versions()->max('version') + 1;
            $draft = $spatialDataset->versions()->create([
                'version' => $nextVersion,
                'status' => DatasetFormVersionStatus::Draft,
                'created_by' => $request->user()->id,
            ]);

            foreach ($sourceVersion->fields as $field) {
                $draft->fields()->create(Arr::except($field->getAttributes(), [
                    'id', 'dataset_form_version_id', 'created_at', 'updated_at',
                ]));
            }

            return $draft;
        });

        $audit->log(null, 'dataset_form_draft_created', $request->user(), [
            'spatial_dataset_id' => $spatialDataset->id,
            'source_form_version_id' => $sourceVersion->id,
            'form_version_id' => $draft->id,
            'version' => $draft->version,
        ], 'data_catalog');

        return back()->with('status', "Versión {$draft->version} creada como borrador editable.");
    }
}
