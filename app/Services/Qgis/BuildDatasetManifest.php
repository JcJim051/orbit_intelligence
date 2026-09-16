<?php

namespace App\Services\Qgis;

use App\Enums\DatasetFormVersionStatus;
use App\Models\DatasetFormField;
use App\Models\SpatialDataset;

class BuildDatasetManifest
{
    /**
     * @return array<string, mixed>
     */
    public function build(SpatialDataset $dataset): array
    {
        $version = $dataset->versions()
            ->where('status', DatasetFormVersionStatus::Published->value)
            ->with('fields')
            ->latest('version')
            ->firstOrFail();

        $fields = $version->fields
            ->where('visible_in_qgis', true)
            ->values()
            ->map(fn (DatasetFormField $field): array => [
                'key' => $field->key,
                'label' => $field->label,
                'section' => $field->section,
                'help_text' => $field->help_text,
                'type' => $field->field_type->value,
                'unit' => $field->unit,
                'required' => $field->required,
                'options' => $field->options ?? [],
                'validation' => $field->validation_rules ?? (object) [],
                'historical_policy' => $field->historical_policy->value,
                'introduced_in_version' => $field->introduced_in_version,
                'public_visible' => $field->public_visible,
                'available_for_analytics' => $field->available_for_analytics,
            ]);

        return [
            'dataset' => [
                'name' => $dataset->name,
                'slug' => $dataset->slug,
                'sector' => $dataset->sector,
                'description' => $dataset->description,
                'geometry_type' => $dataset->geometry_type,
                'srid' => $dataset->storage_srid,
                'database_schema' => 'capture',
                'database_table' => $dataset->physical_table,
            ],
            'form' => [
                'version' => $version->version,
                'effective_from' => $version->effective_from?->toDateString(),
                'published_at' => $version->published_at?->toIso8601String(),
            ],
            'system_fields' => array_values(array_filter([
                ['key' => 'id', 'type' => 'uuid', 'editable' => false],
                $dataset->geometry_type === 'none' ? null : ['key' => 'geom', 'type' => 'geometry', 'editable' => true],
                ['key' => 'form_version', 'type' => 'integer', 'editable' => false],
                ['key' => 'record_status', 'type' => 'select', 'editable' => true, 'options' => ['draft', 'submitted']],
                ['key' => 'source', 'type' => 'short_text', 'editable' => true],
                ['key' => 'created_by_email', 'type' => 'short_text', 'editable' => false],
                ['key' => 'created_at', 'type' => 'datetime', 'editable' => false],
                ['key' => 'updated_at', 'type' => 'datetime', 'editable' => false],
            ])),
            'sections' => $fields->pluck('section')->unique()->values()->all(),
            'fields' => $fields->all(),
            'coverage_notice' => 'Los campos sólo tienen cobertura garantizada desde su versión de introducción; consulte historical_policy e introduced_in_version.',
        ];
    }
}
