<?php

namespace App\Services\Dashboards;

use App\Enums\DatasetFormVersionStatus;
use App\Models\GeoViewer;
use App\Models\SpatialDataset;
use App\Models\TabularDataSource;
use App\Models\TabularDataSourceVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DashboardRelationshipDiagnostic
{
    /** @return array<string, mixed> */
    public function run(TabularDataSource $source, GeoViewer $viewer, string $dataField, string $layerField): array
    {
        $versionQuery = $source->currentVersion();
        $version = DB::getDriverName() === 'pgsql'
            ? $versionQuery->select(['id', 'tabular_data_source_id', 'fields'])->firstOrFail()
            : $versionQuery->firstOrFail();
        $dataDefinition = collect($version->fields)->firstWhere('key', $dataField);
        if ($dataDefinition === null) {
            throw new RuntimeException('El campo de relación no existe en la fuente tabular.');
        }
        $layer = $viewer->layers()->get()->first(fn ($layer): bool => $layer->source_type === 'geojson' && str_starts_with($layer->source_url, '/api/public/geodata/'));
        if ($layer === null) {
            throw new RuntimeException('El geovisor no contiene una capa administrada por SIID que pueda relacionarse.');
        }
        $slug = basename(parse_url($layer->source_url, PHP_URL_PATH));
        $dataset = SpatialDataset::query()->where('slug', $slug)->with(['versions' => fn ($query) => $query->where('status', DatasetFormVersionStatus::Published->value)->with('fields')->orderByDesc('version')])->firstOrFail();
        if (blank($dataset->physical_table)) {
            throw new RuntimeException('La capa elegida todavía no tiene una tabla publicada para hacer la relación.');
        }
        $layerDefinition = $dataset->versions->first()?->fields->firstWhere('key', $layerField);
        if ($layerDefinition === null || ! $layerDefinition->public_visible) {
            throw new RuntimeException('El campo territorial de la capa no existe o no está autorizado para consulta pública.');
        }
        $table = $this->quoteIdentifier((string) $dataset->physical_table);
        $column = $this->quoteIdentifier($layerField);
        $layerValues = collect(DB::select("SELECT DISTINCT {$column}::text AS value FROM publication.{$table} WHERE {$column} IS NOT NULL"))->pluck('value')->map(fn ($value): string => trim((string) $value))->filter()->unique();
        $dataValueCounts = $this->dataValueCounts($version, $dataField);
        $duplicates = $dataValueCounts->filter(fn (int $count): bool => $count > 1);
        $uniqueData = $dataValueCounts->keys();

        return [
            'matched' => $uniqueData->intersect($layerValues)->count(),
            'data_without_geometry' => $uniqueData->diff($layerValues)->values()->take(100)->all(),
            'geometry_without_data' => $layerValues->diff($uniqueData)->values()->take(100)->all(),
            'duplicate_codes' => $duplicates->keys()->take(100)->all(),
            'type_warning' => $dataDefinition['type'] !== 'text' && ! in_array($layerDefinition->field_type->value, ['integer', 'decimal'], true)
                ? 'Los campos parecen tener tipos diferentes; conviene tratarlos como texto para conservar ceros iniciales.' : null,
        ];
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    /**
     * @return Collection<string, int>
     */
    private function dataValueCounts(TabularDataSourceVersion $version, string $dataField): Collection
    {
        if (DB::getDriverName() !== 'pgsql') {
            return collect($version->records)
                ->pluck($dataField)
                ->map(fn ($value): string => trim((string) $value))
                ->filter()
                ->countBy();
        }

        return collect(DB::select(<<<'SQL'
            WITH territorial_codes AS MATERIALIZED (
                SELECT NULLIF(BTRIM(jsonb_extract_path_text(item, ?)), '') AS value
                FROM tabular_data_source_versions AS versions
                CROSS JOIN LATERAL jsonb_array_elements(versions.records) AS item
                WHERE versions.id = ?
            )
            SELECT value, COUNT(*)::integer AS total
            FROM territorial_codes
            WHERE value IS NOT NULL
            GROUP BY value
            SQL, [$dataField, $version->id]))
            ->mapWithKeys(fn (object $row): array => [(string) $row->value => (int) $row->total]);
    }
}
