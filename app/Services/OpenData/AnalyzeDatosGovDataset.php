<?php

namespace App\Services\OpenData;

use App\Services\Geovisors\GetMetaMunicipalBoundaries;
use App\Services\Investments\SocrataClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class AnalyzeDatosGovDataset
{
    public function __construct(
        private readonly DatosGovUrl $urls,
        private readonly SocrataClient $socrata,
        private readonly GetMetaMunicipalBoundaries $boundaries,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $url): array
    {
        $parsed = $this->urls->parse($url);
        $metadata = $this->socrata->metadata($parsed['dataset_id']);
        $columns = collect($metadata['columns'] ?? [])->map(fn (array $column): array => [
            'field' => (string) ($column['fieldName'] ?? ''),
            'label' => (string) ($column['name'] ?? $column['fieldName'] ?? ''),
            'type' => mb_strtolower((string) ($column['dataTypeName'] ?? 'text')),
        ])->filter(fn (array $column): bool => $column['field'] !== '')->values();

        if ($columns->isEmpty()) {
            throw new RuntimeException('Datos.gov.co no informó columnas utilizables para este conjunto.');
        }

        $allSample = collect(iterator_to_array($this->socrata->rows($parsed['dataset_id'], maximum: 150)));
        $territorialFilter = $this->territorialFilterProposal($parsed['dataset_id'], $columns->all(), $allSample);
        $sample = $territorialFilter['available']
            ? $this->filteredSample($parsed['dataset_id'], $territorialFilter, $allSample)
            : $allSample;
        $count = (int) collect($metadata['columns'] ?? [])->max(fn (array $column): int => (int) data_get($column, 'cachedContents.count', 0));
        if ($count === 0) {
            try {
                $count = (int) data_get($this->socrata->query($parsed['dataset_id'], ['$select' => 'count(*) as total', '$limit' => 1]), '0.total', $allSample->count());
            } catch (\Throwable) {
                $count = $sample->count();
            }
        }
        $proposal = $this->geographyProposal($parsed['dataset_id'], $columns->all(), $sample->all());
        if ($proposal === null) {
            throw new RuntimeException('El conjunto no contiene geometría, coordenadas ni un código DANE municipal confiable.');
        }

        $filters = $columns->filter(function (array $column) use ($sample): bool {
            $field = $column['field'];
            $values = $sample->pluck($field)->filter(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '')->unique();
            $semanticFilter = (bool) preg_match('/a.o|anio|year|vigencia|categor|tipo|clase|sexo|zona|cultivo/i', $field.' '.$column['label']);
            $numeric = in_array($column['type'], ['number', 'money', 'double'], true);
            $geographicKey = (bool) preg_match('/dane|cod.*mun|mun.*cod|latitud|longitud|latitude|longitude/i', $field.' '.$column['label']);

            return $values->isNotEmpty() && $values->count() <= 100
                && ! $geographicKey
                && ($semanticFilter || (! $numeric && $values->count() <= 20));
        })->take(4)->map(function (array $column) use ($sample): array {
            return [
                'field' => $column['field'],
                'label' => $column['label'],
                'options' => $sample->pluck($column['field'])
                    ->filter(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '')
                    ->map(fn ($value): string => (string) $value)->unique()->sort()->take(100)->values()->all(),
            ];
        })->values()->all();

        $numeric = $columns->filter(fn (array $column): bool => in_array($column['type'], ['number', 'money', 'double'], true))->values()->all();
        $popup = $columns->reject(fn (array $column): bool => str_starts_with($column['field'], ':'))->take(12)->pluck('field')->all();
        $schemaSignature = $this->schemaSignature($columns->all());

        return [
            'dataset_id' => $parsed['dataset_id'],
            'landing_page_url' => $parsed['landing_page_url'],
            'title' => (string) ($metadata['name'] ?? 'Conjunto de Datos.gov.co'),
            'description' => (string) ($metadata['description'] ?? ''),
            'attribution' => (string) data_get($metadata, 'metadata.custom_fields.Common.Publisher', $metadata['attribution'] ?? 'Datos.gov.co'),
            'license' => is_array($metadata['license'] ?? null)
                ? (string) ($metadata['license']['name'] ?? 'Consulte la portada oficial')
                : (string) ($metadata['license'] ?? 'Consulte la portada oficial'),
            'updated_at' => isset($metadata['rowsUpdatedAt']) ? date(DATE_ATOM, (int) $metadata['rowsUpdatedAt']) : null,
            'record_count' => $count,
            'columns' => $columns->all(),
            'numeric_fields' => $numeric,
            'suggested_popup_fields' => $popup,
            'suggested_filters' => $filters,
            'schema_signature' => $schemaSignature,
            'geography' => $proposal,
            'preview' => $this->preview($proposal, $sample->all(), $popup),
            'preview_all' => $this->preview($proposal, $allSample->all(), $popup),
            'territorial_filter' => $territorialFilter,
            'metadata' => [
                'name' => $metadata['name'] ?? null,
                'description' => $metadata['description'] ?? null,
                'columns' => $columns->all(),
                'rows_updated_at' => $metadata['rowsUpdatedAt'] ?? null,
            ],
        ];
    }

    /** @param array<int, array<string, string>> $columns @param Collection<int, array<string, mixed>> $sample @return array<string, mixed> */
    private function territorialFilterProposal(string $datasetId, array $columns, Collection $sample): array
    {
        $candidates = collect($columns)->filter(function (array $column): bool {
            $name = Str::ascii(mb_strtolower($column['field'].' '.$column['label']));

            return (bool) preg_match('/departamento|depto|dpto|cod.*dep|dep.*cod|dane.*dep/', $name)
                && ! preg_match('/municip|mpio/', $name);
        });

        foreach ($candidates as $candidate) {
            $field = $candidate['field'];
            $values = $sample->pluck($field)->filter(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '')->unique()->values();
            $metaValue = $values->first(fn ($value): bool => $this->isMetaDepartmentValue($value));

            if ($metaValue === null) {
                try {
                    $values = collect($this->socrata->query($datasetId, [
                        '$select' => $field,
                        '$group' => $field,
                        '$order' => $field,
                        '$limit' => 100,
                    ]))->pluck($field)->filter(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '')->unique()->values();
                    $metaValue = $values->first(fn ($value): bool => $this->isMetaDepartmentValue($value));
                } catch (\Throwable) {
                    $metaValue = null;
                }
            }

            if ($metaValue !== null) {
                return [
                    'available' => true,
                    'field' => $field,
                    'field_label' => $candidate['label'],
                    'value' => (string) $metaValue,
                    'field_type' => $candidate['type'],
                ];
            }
        }

        return [
            'available' => false,
            'field' => null,
            'field_label' => null,
            'value' => 'Meta',
            'field_type' => null,
        ];
    }

    /** @param array<string, mixed> $territorialFilter @param Collection<int, array<string, mixed>> $fallback @return Collection<int, array<string, mixed>> */
    private function filteredSample(string $datasetId, array $territorialFilter, Collection $fallback): Collection
    {
        try {
            $rows = collect(iterator_to_array($this->socrata->rows(
                $datasetId,
                $this->scopeCondition(
                    (string) $territorialFilter['field'],
                    (string) $territorialFilter['value'],
                    (string) $territorialFilter['field_type'],
                ),
                maximum: 150,
            )));

            return $rows->isNotEmpty() ? $rows : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function scopeCondition(string $field, string $value, string $type): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
            throw new RuntimeException('Datos.gov.co informó un campo territorial no permitido.');
        }
        if (in_array($type, ['number', 'money', 'double'], true) && is_numeric($value)) {
            return $field.' = '.(string) (0 + $value);
        }

        return $field." = '".str_replace("'", "''", $value)."'";
    }

    private function isMetaDepartmentValue(mixed $value): bool
    {
        $normalized = mb_strtoupper(trim(Str::ascii((string) $value)));
        if (preg_match('/(^|[^A-Z])META([^A-Z]|$)/', $normalized)) {
            return true;
        }

        return preg_match('/^0*50(?:\.0+)?$/', $normalized) === 1;
    }

    /** @param array<string, mixed> $proposal @param array<int, array<string, mixed>> $sample @param array<int, string> $popup */
    private function preview(array $proposal, array $sample, array $popup): array
    {
        if ($proposal['mode'] === 'dane_municipality') {
            return [
                'type' => 'FeatureCollection',
                'features' => collect($this->boundaries->handle()['features'] ?? [])->map(fn (array $feature): array => [
                    'type' => 'Feature',
                    'properties' => [
                        'codigo_dane' => data_get($feature, 'properties.mpio_cdpmp'),
                        'municipio' => data_get($feature, 'properties.mpio_cnmbr'),
                    ],
                    'geometry' => $feature['geometry'] ?? null,
                ])->filter(fn (array $feature): bool => is_array($feature['geometry']))->values()->all(),
            ];
        }

        $features = collect($sample)->take(250)->map(function (array $row) use ($proposal, $popup): ?array {
            if ($proposal['mode'] === 'coordinates') {
                $lat = $row[$proposal['latitude_field']] ?? null;
                $lon = $row[$proposal['longitude_field']] ?? null;
                $geometry = is_numeric($lat) && is_numeric($lon)
                    ? ['type' => 'Point', 'coordinates' => [(float) $lon, (float) $lat]]
                    : null;
            } else {
                $value = $row[$proposal['geometry_field']] ?? null;
                $geometry = is_array($value) && isset($value['type'], $value['coordinates'])
                    ? ['type' => $value['type'], 'coordinates' => $value['coordinates']]
                    : (is_array($value) && is_numeric($value['latitude'] ?? null) && is_numeric($value['longitude'] ?? null)
                        ? ['type' => 'Point', 'coordinates' => [(float) $value['longitude'], (float) $value['latitude']]]
                        : null);
            }

            return $geometry ? ['type' => 'Feature', 'properties' => collect($row)->only($popup)->all(), 'geometry' => $geometry] : null;
        })->filter()->values()->all();

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /** @param array<int, array<string, string>> $columns @param array<int, array<string, mixed>> $sample */
    private function geographyProposal(string $datasetId, array $columns, array $sample): ?array
    {
        $geometry = collect($columns)->first(fn (array $column): bool => in_array($column['type'], ['point', 'multipoint', 'line', 'multiline', 'polygon', 'multipolygon', 'location'], true));
        if ($geometry) {
            return ['mode' => 'geometry', 'geometry_field' => $geometry['field'], 'diagnostics' => ['sampled' => count($sample)]];
        }

        $latitude = collect($columns)->first(fn (array $column): bool => preg_match('/(^|_)(lat|latitud|latitude)($|_)/i', $column['field'].'_'.$column['label']));
        $longitude = collect($columns)->first(fn (array $column): bool => preg_match('/(^|_)(lon|lng|longitud|longitude)($|_)/i', $column['field'].'_'.$column['label']));
        if ($latitude && $longitude) {
            return ['mode' => 'coordinates', 'latitude_field' => $latitude['field'], 'longitude_field' => $longitude['field'], 'diagnostics' => ['sampled' => count($sample)]];
        }

        $daneCandidates = collect($columns)
            ->filter(fn (array $column): bool => preg_match('/dane|cod.*mun|mun.*cod|dpmp|mpio/i', $column['field'].' '.$column['label']))
            ->reject(function (array $column): bool {
                $name = $column['field'].' '.$column['label'];

                return (bool) preg_match('/departamento|depto|dpto/i', $name)
                    && ! preg_match('/municip|mpio|dpmp/i', $name);
            })
            ->sortByDesc(fn (array $column): int => preg_match('/municip|mpio|dpmp|cod.*mun|mun.*cod/i', $column['field'].' '.$column['label']) ? 1 : 0);
        if ($daneCandidates->isEmpty()) {
            return null;
        }

        $official = collect($this->boundaries->handle()['features'] ?? [])
            ->map(fn (array $feature): string => $this->municipalityCode(data_get($feature, 'properties.mpio_cdpmp')))
            ->filter()
            ->unique();
        foreach ($daneCandidates as $candidate) {
            $codes = collect($sample)->pluck($candidate['field'])->map(fn ($value): string => $this->municipalityCode($value))->filter()->values();
            $matched = $codes->filter(fn (string $code): bool => $official->contains($code));

            if ($codes->isEmpty() || $matched->count() / max(1, $codes->count()) < .8) {
                $codes = $this->metaMunicipalitySample($datasetId, $candidate);
                $matched = $codes->filter(fn (string $code): bool => $official->contains($code));
            }

            if ($codes->isNotEmpty() && $matched->count() / $codes->count() >= .8) {
                return [
                    'mode' => 'dane_municipality',
                    'dane_field' => $candidate['field'],
                    'diagnostics' => [
                        'sampled' => $codes->count(),
                        'matched' => $matched->count(),
                        'unmatched' => $codes->diff($official)->unique()->values()->all(),
                        'duplicate_codes' => $codes->duplicates()->unique()->values()->all(),
                        'meta_municipalities' => $official->count(),
                    ],
                ];
            }
        }

        return null;
    }

    /** @param array<string, string> $candidate */
    private function metaMunicipalitySample(string $datasetId, array $candidate): Collection
    {
        $field = $candidate['field'];
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
            return collect();
        }

        $where = in_array($candidate['type'], ['number', 'money', 'double'], true)
            ? "{$field} between 50000 and 50999"
            : "{$field} between '50000' and '50999'";

        try {
            return collect($this->socrata->query($datasetId, [
                '$select' => $field,
                '$where' => $where,
                '$limit' => 150,
            ]))->pluck($field)->map(fn ($value): string => $this->municipalityCode($value))->filter()->values();
        } catch (\Throwable) {
            return collect();
        }
    }

    /** @param array<int, array<string, string>> $columns */
    public function schemaSignature(array $columns): string
    {
        return hash('sha256', json_encode(collect($columns)->map(fn (array $column): array => [
            $column['field'] ?? $column['fieldName'] ?? '',
            mb_strtolower((string) ($column['type'] ?? $column['dataTypeName'] ?? '')),
        ])->sortBy(0)->values()->all(), JSON_UNESCAPED_UNICODE));
    }

    private function municipalityCode(mixed $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';
        if ($digits === '') {
            return '';
        }

        return str_pad(substr($digits, -5), 5, '0', STR_PAD_LEFT);
    }
}
