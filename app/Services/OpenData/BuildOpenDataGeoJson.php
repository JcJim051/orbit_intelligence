<?php

namespace App\Services\OpenData;

use App\Models\OpenDataSnapshot;
use App\Models\OpenDataSource;
use App\Services\Geovisors\GetMetaMunicipalBoundaries;
use App\Services\Investments\SocrataClient;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class BuildOpenDataGeoJson
{
    public function __construct(
        private readonly SocrataClient $socrata,
        private readonly GetMetaMunicipalBoundaries $boundaries,
        private readonly AnalyzeDatosGovDataset $analyzer,
    ) {}

    /** @param array<string, scalar|null> $requestedFilters @return array<string, mixed> */
    public function handle(OpenDataSource $source, array $requestedFilters = [], bool $force = false): array
    {
        $filters = $this->allowedFilters($source, $requestedFilters);
        $queryHash = hash('sha256', json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $snapshot = $source->snapshots()->where('query_hash', $queryHash)->first();

        if (! $force && $snapshot?->refreshed_at?->isAfter(now()->subHours((int) config('open-data.refresh_hours', 6)))) {
            $payload = $snapshot->payload;
            if (in_array($source->status, ['needs_review', 'error'], true)) {
                $payload['metadata']['stale'] = true;
                $payload['metadata']['warning'] = $source->last_error;
            }

            return $payload;
        }

        try {
            $payload = $this->refresh($source, $filters);
            OpenDataSnapshot::query()->updateOrCreate(
                ['open_data_source_id' => $source->id, 'query_hash' => $queryHash],
                [
                    'filters' => $filters,
                    'payload' => $payload,
                    'feature_count' => count($payload['features'] ?? []),
                    'checksum' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    'refreshed_at' => now(),
                ],
            );
            $source->update(['status' => 'ready', 'last_checked_at' => now(), 'last_success_at' => now(), 'last_error' => null]);

            return $payload;
        } catch (Throwable $exception) {
            $source->update([
                'status' => $exception->getMessage() === 'SCHEMA_CHANGED' ? 'needs_review' : 'error',
                'last_checked_at' => now(),
                'last_error' => $exception->getMessage() === 'SCHEMA_CHANGED'
                    ? 'La estructura del conjunto cambió. SIID conserva la última versión válida hasta que sea revisada.'
                    : mb_substr($exception->getMessage(), 0, 2000),
            ]);

            if ($snapshot) {
                $payload = $snapshot->payload;
                $payload['metadata']['stale'] = true;
                $payload['metadata']['warning'] = $source->fresh()->last_error;

                return $payload;
            }

            throw $exception;
        }
    }

    /** @param array<string, scalar|null> $filters @return array<string, mixed> */
    private function refresh(OpenDataSource $source, array $filters): array
    {
        $metadata = $this->socrata->metadata($source->dataset_id);
        $columns = collect($metadata['columns'] ?? [])->map(fn (array $column): array => [
            'field' => (string) ($column['fieldName'] ?? ''),
            'type' => mb_strtolower((string) ($column['dataTypeName'] ?? 'text')),
        ])->filter(fn (array $column): bool => $column['field'] !== '')->values()->all();
        if (! hash_equals($source->schema_signature, $this->analyzer->schemaSignature($columns))) {
            throw new RuntimeException('SCHEMA_CHANGED');
        }

        $where = $this->where($source, $filters, $columns);
        $features = $source->geography_mode === 'dane_municipality'
            ? $this->municipalFeatures($source, $where, $columns)
            : $this->directFeatures($source, $where);

        return [
            'type' => 'FeatureCollection',
            'metadata' => [
                'source' => $source->name,
                'provider' => 'Datos.gov.co',
                'source_page_url' => $source->landing_page_url,
                'dataset_id' => $source->dataset_id,
                'source_updated_at' => isset($metadata['rowsUpdatedAt']) ? date(DATE_ATOM, (int) $metadata['rowsUpdatedAt']) : null,
                'refreshed_at' => now()->toIso8601String(),
                'filters' => $filters,
                'stale' => false,
            ],
            'features' => $features,
        ];
    }

    /** @param array<string, scalar|null> $requested @return array<string, scalar> */
    private function allowedFilters(OpenDataSource $source, array $requested): array
    {
        $configured = collect($source->filters ?? [])->keyBy('field');
        $validated = [];
        foreach ($requested as $field => $value) {
            if (! $configured->has($field) || ! is_scalar($value) || mb_strlen((string) $value) > 200) {
                throw new InvalidArgumentException('Se recibió un filtro no autorizado.');
            }
            $definition = $configured->get($field);
            $options = array_map('strval', $definition['options'] ?? []);
            if ($options !== [] && ! in_array((string) $value, $options, true)) {
                throw new InvalidArgumentException('El valor del filtro no está autorizado.');
            }
            $validated[$field] = $value;
        }
        ksort($validated);

        return $validated;
    }

    /** @param array<int, array<string, string>> $columns */
    private function where(OpenDataSource $source, array $filters, array $columns): ?string
    {
        if ($filters === []) {
            return null;
        }
        $types = collect($columns)->pluck('type', 'field');

        return collect($filters)->map(function ($value, string $field) use ($types): string {
            $this->assertIdentifier($field);
            if (in_array($types->get($field), ['number', 'money', 'double'], true) && is_numeric($value)) {
                return $field.' = '.(string) (0 + $value);
            }

            return $field." = '".str_replace("'", "''", (string) $value)."'";
        })->implode(' AND ');
    }

    /** @param array<int, array<string, string>> $columns @return array<int, array<string, mixed>> */
    private function municipalFeatures(OpenDataSource $source, ?string $where, array $columns): array
    {
        $dane = $this->assertIdentifier((string) $source->dane_field);
        $operation = in_array($source->aggregation, ['sum', 'avg', 'min', 'max', 'count'], true) ? $source->aggregation : 'count';
        $metric = $source->metric_field ? $this->assertIdentifier($source->metric_field) : null;
        if ($operation !== 'count' && $metric === null) {
            throw new RuntimeException('La agregación requiere un campo numérico.');
        }
        $metricType = collect($columns)->firstWhere('field', $metric)['type'] ?? null;
        if ($metric && ! in_array($metricType, ['number', 'money', 'double'], true)) {
            throw new RuntimeException('El campo indicador dejó de ser numérico.');
        }

        $expression = $operation === 'count' ? 'count(*)' : "{$operation}({$metric})";
        $query = [
            '$select' => "{$dane}, {$expression} as siid_value",
            '$group' => $dane,
            '$order' => $dane,
            '$limit' => 100,
        ];
        if ($where) {
            $query['$where'] = $where;
        }
        $values = collect($this->socrata->query($source->dataset_id, $query))->mapWithKeys(fn (array $row): array => [
            $this->municipalityCode($row[$dane] ?? null) => is_numeric($row['siid_value'] ?? null) ? (float) $row['siid_value'] : null,
        ]);

        return collect($this->boundaries->handle()['features'] ?? [])->map(function (array $feature) use ($values, $source): array {
            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $code = $this->municipalityCode($properties['mpio_cdpmp'] ?? null);

            return [
                'type' => 'Feature',
                'properties' => [
                    'codigo_dane' => $code,
                    'municipio' => $properties['mpio_cnmbr'] ?? null,
                    'valor' => $values->get($code),
                    'indicador' => $source->metric_field ?: 'Registros',
                    'agregacion' => $source->aggregation,
                ],
                'geometry' => $feature['geometry'] ?? null,
            ];
        })->filter(fn (array $feature): bool => is_array($feature['geometry']))->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function directFeatures(OpenDataSource $source, ?string $where): array
    {
        $fields = collect($source->popup_fields ?? [])->map(fn ($field): string => $this->assertIdentifier((string) $field));
        foreach ([$source->geometry_field, $source->latitude_field, $source->longitude_field] as $field) {
            if ($field) {
                $fields->push($this->assertIdentifier($field));
            }
        }
        $query = ['$select' => $fields->unique()->implode(','), '$limit' => min(5000, (int) config('open-data.maximum_features', 5000))];
        if ($where) {
            $query['$where'] = $where;
        }

        return collect($this->socrata->query($source->dataset_id, $query))->map(function (array $row) use ($source): ?array {
            $geometry = $this->geometry($source, $row);
            if ($geometry === null) {
                return null;
            }

            return [
                'type' => 'Feature',
                'properties' => Arr::only($row, $source->popup_fields ?? []),
                'geometry' => $geometry,
            ];
        })->filter()->values()->all();
    }

    /** @param array<string, mixed> $row @return array<string, mixed>|null */
    private function geometry(OpenDataSource $source, array $row): ?array
    {
        if ($source->geography_mode === 'coordinates') {
            $lat = $row[$source->latitude_field] ?? null;
            $lon = $row[$source->longitude_field] ?? null;
            if (! is_numeric($lat) || ! is_numeric($lon) || abs((float) $lat) > 90 || abs((float) $lon) > 180) {
                return null;
            }

            return ['type' => 'Point', 'coordinates' => [(float) $lon, (float) $lat]];
        }

        $value = $row[$source->geometry_field] ?? null;
        if (is_array($value) && isset($value['type'], $value['coordinates'])) {
            return ['type' => $value['type'], 'coordinates' => $value['coordinates']];
        }
        if (is_array($value) && is_numeric($value['latitude'] ?? null) && is_numeric($value['longitude'] ?? null)) {
            return ['type' => 'Point', 'coordinates' => [(float) $value['longitude'], (float) $value['latitude']]];
        }

        return null;
    }

    private function assertIdentifier(string $field): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
            throw new RuntimeException('La configuración contiene un campo no permitido.');
        }

        return $field;
    }

    private function municipalityCode(mixed $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        return $digits === '' ? '' : str_pad(substr($digits, -5), 5, '0', STR_PAD_LEFT);
    }
}
