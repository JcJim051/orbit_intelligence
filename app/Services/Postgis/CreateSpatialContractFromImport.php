<?php

namespace App\Services\Postgis;

use App\Enums\DatasetFieldType;
use App\Enums\DatasetFormVersionStatus;
use App\Enums\DatasetStatus;
use App\Enums\HistoricalDataPolicy;
use App\Enums\SpatialImportStatus;
use App\Models\SpatialDataset;
use App\Models\SpatialImport;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateSpatialContractFromImport
{
    public function __construct(private ProvisionSpatialImportStaging $staging) {}

    /** @param array{name: string, slug: string, description?: string|null, table: string, storage_srid: int|string} $data */
    public function create(SpatialImport $import, User $actor, array $data): SpatialDataset
    {
        $table = collect(Arr::get($import->profile, 'tables', []))->firstWhere('name', $data['table']);
        if (! is_array($table)) {
            throw new RuntimeException('La tabla elegida no pertenece al último perfil de esta importación.');
        }
        if ($import->contracts()->where('source_table', $data['table'])->exists()
            || $import->selected_table === $data['table']) {
            throw new RuntimeException('Esta capa ya tiene un contrato. Revise su conjunto en el catálogo de datos.');
        }
        if (Arr::has($table, 'geometries.0') && (int) Arr::get($table, 'geometries.0.srid', 0) <= 0) {
            throw new RuntimeException('La capa no tiene un CRS de origen identificable. Corrija el sistema de coordenadas en QGIS antes de crear el contrato.');
        }

        return DB::transaction(function () use ($import, $actor, $data, $table): SpatialDataset {
            $dataset = SpatialDataset::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'sector' => $import->sector,
                'description' => $data['description'] ?? $import->purpose,
                'geometry_type' => $this->geometryType($table),
                'storage_srid' => (int) $data['storage_srid'],
                'status' => DatasetStatus::Draft,
                'created_by' => $actor->id,
            ]);
            $version = $dataset->versions()->create([
                'version' => 1,
                'status' => DatasetFormVersionStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $mapping = [];
            $used = [];
            $sort = 10;
            foreach (Arr::get($table, 'columns', []) as $column) {
                if (($column['udt_name'] ?? null) === 'geometry') {
                    continue;
                }

                $source = (string) $column['name'];
                $key = $this->uniqueKey($source, $used);
                $used[] = $key;
                $mapping[$source] = $key;
                $version->fields()->create([
                    'key' => $key,
                    'label' => Str::headline($source),
                    'section' => 'Datos importados',
                    'help_text' => 'Campo de origen: '.$source.'. Revise su nombre, tipo y reglas antes de publicar.',
                    'field_type' => $this->fieldType((string) ($column['data_type'] ?? 'text')),
                    'required' => false,
                    'historical_policy' => HistoricalDataPolicy::OptionalBackfill,
                    'introduced_in_version' => 1,
                    'visible_in_qgis' => true,
                    'public_visible' => false,
                    'available_for_analytics' => true,
                    'sort_order' => $sort,
                ]);
                $sort += 10;
            }

            if ($mapping === []) {
                throw new RuntimeException('La tabla no contiene columnas de atributos para construir el contrato.');
            }

            if (Arr::has($table, 'geometries.0')) {
                $mapping['_geometry'] = [
                    'source' => Arr::get($table, 'geometries.0.column'),
                    'type' => Arr::get($table, 'geometries.0.type'),
                    'srid' => Arr::get($table, 'geometries.0.srid'),
                ];
            }

            $this->staging->freeze($import);
            $import->contracts()->create([
                'source_table' => $data['table'],
                'field_mapping' => $mapping,
                'spatial_dataset_id' => $dataset->id,
                'status' => SpatialImportStatus::ContractDraft,
            ]);
            if ($import->spatial_dataset_id === null) {
                $import->update([
                    'status' => SpatialImportStatus::ContractDraft,
                    'selected_table' => $data['table'],
                    'field_mapping' => $mapping,
                    'spatial_dataset_id' => $dataset->id,
                ]);
            }

            return $dataset;
        });
    }

    /** @param array<string, mixed> $table */
    private function geometryType(array $table): string
    {
        $type = mb_strtoupper((string) Arr::get($table, 'geometries.0.type', ''));

        return match (true) {
            str_contains($type, 'POINT') => 'point',
            str_contains($type, 'LINE') => 'line',
            str_contains($type, 'POLYGON') => 'polygon',
            default => 'none',
        };
    }

    private function fieldType(string $type): DatasetFieldType
    {
        return match ($type) {
            'smallint', 'integer', 'bigint' => DatasetFieldType::Integer,
            'numeric', 'decimal', 'real', 'double precision' => DatasetFieldType::Decimal,
            'date' => DatasetFieldType::Date,
            'timestamp without time zone', 'timestamp with time zone' => DatasetFieldType::DateTime,
            'boolean' => DatasetFieldType::Boolean,
            'text', 'json', 'jsonb', 'ARRAY' => DatasetFieldType::LongText,
            default => DatasetFieldType::ShortText,
        };
    }

    /** @param array<int, string> $used */
    private function uniqueKey(string $source, array $used): string
    {
        $reserved = ['fid', 'id', 'geom', 'form_version', 'record_status', 'source', 'created_by_email', 'created_at', 'updated_at'];
        $base = Str::of(Str::ascii($source))->snake()->lower()->replaceMatches('/[^a-z0-9_]/', '')->trim('_')->limit(48, '')->toString();
        $base = $base === '' ? 'campo' : $base;
        if (in_array($base, $reserved, true)) {
            $base = 'dato_'.$base;
        }
        $key = $base;
        $suffix = 2;
        while (in_array($key, $used, true)) {
            $key = mb_substr($base, 0, 44).'_'.$suffix++;
        }

        return $key;
    }
}
