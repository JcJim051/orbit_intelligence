<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDatasetFormFieldRequest;
use App\Http\Requests\UpdateDatasetFormFieldRequest;
use App\Models\DatasetFormField;
use App\Models\DatasetFormVersion;
use App\Models\SpatialDataset;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DatasetFormFieldController extends Controller
{
    public function store(
        StoreDatasetFormFieldRequest $request,
        SpatialDataset $spatialDataset,
        DatasetFormVersion $version,
        AuditLogger $audit,
    ): RedirectResponse {
        $field = $version->fields()->create([
            ...$this->attributes($request),
            'introduced_in_version' => $version->version,
        ]);
        $audit->log(null, 'dataset_form_field_created', $request->user(), [
            'spatial_dataset_id' => $spatialDataset->id,
            'form_version_id' => $version->id,
            'field_id' => $field->id,
        ], 'data_catalog', [], $field->toArray());

        return back()->with('status', 'Campo agregado a la versión borrador.');
    }

    public function update(
        UpdateDatasetFormFieldRequest $request,
        SpatialDataset $spatialDataset,
        DatasetFormVersion $version,
        DatasetFormField $field,
        AuditLogger $audit,
    ): RedirectResponse {
        $old = $field->toArray();
        $field->update($this->attributes($request));
        $audit->log(null, 'dataset_form_field_updated', $request->user(), [
            'spatial_dataset_id' => $spatialDataset->id,
            'form_version_id' => $version->id,
            'field_id' => $field->id,
        ], 'data_catalog', $old, $field->fresh()->toArray());

        return back()->with('status', 'Campo actualizado.');
    }

    public function destroy(
        Request $request,
        SpatialDataset $spatialDataset,
        DatasetFormVersion $version,
        DatasetFormField $field,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($request->user()?->isAdmin() && $version->isDraft(), 403);
        $old = $field->toArray();
        $field->delete();
        $audit->log(null, 'dataset_form_field_deleted', $request->user(), [
            'spatial_dataset_id' => $spatialDataset->id,
            'form_version_id' => $version->id,
            'field_id' => $field->id,
        ], 'data_catalog', $old);

        return back()->with('status', 'Campo retirado del borrador.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreDatasetFormFieldRequest|UpdateDatasetFormFieldRequest $request): array
    {
        $options = collect(preg_split('/[\r\n,]+/', $request->string('options')->toString()))
            ->map(fn (string $option): string => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $validationRules = collect([
            'min' => $request->filled('min_value') ? $request->input('min_value') : null,
            'max' => $request->filled('max_value') ? $request->input('max_value') : null,
            'max_length' => $request->filled('max_length') ? $request->integer('max_length') : null,
        ])->filter(fn (mixed $value): bool => $value !== null)->all();

        return [
            ...$request->safe()->only([
                'key', 'label', 'section', 'help_text', 'field_type', 'unit', 'historical_policy', 'sort_order',
            ]),
            'required' => $request->boolean('required'),
            'options' => $options ?: null,
            'validation_rules' => $validationRules ?: null,
            'visible_in_qgis' => $request->boolean('visible_in_qgis'),
            'public_visible' => $request->boolean('public_visible'),
            'available_for_analytics' => $request->boolean('available_for_analytics'),
        ];
    }
}
