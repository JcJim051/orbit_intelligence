<?php

namespace App\Services\OpenData;

use App\Services\Geovisors\GetMetaMunicipalBoundaries;
use App\Services\Investments\SocrataClient;
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

        $sample = collect(iterator_to_array($this->socrata->rows($parsed['dataset_id'], maximum: 150)));
        $count = (int) collect($metadata['columns'] ?? [])->max(fn (array $column): int => (int) data_get($column, 'cachedContents.count', 0));
        if ($count === 0) {
            try {
                $count = (int) data_get($this->socrata->query($parsed['dataset_id'], ['$select' => 'count(*) as total', '$limit' => 1]), '0.total', $sample->count());
            } catch (\Throwable) {
                $count = $sample->count();
            }
        }
        $proposal = $this->geographyProposal($columns->all(), $sample->all());
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
            'metadata' => [
                'name' => $metadata['name'] ?? null,
                'description' => $metadata['description'] ?? null,
                'columns' => $columns->all(),
                'rows_updated_at' => $metadata['rowsUpdatedAt'] ?? null,
            ],
        ];
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
    private function geographyProposal(array $columns, array $sample): ?array
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

        $daneCandidates = collect($columns)->filter(fn (array $column): bool => preg_match('/dane|cod.*mun|mun.*cod|dpmp|mpio/i', $column['field'].' '.$column['label']));
        foreach ($daneCandidates as $candidate) {
            $codes = collect($sample)->pluck($candidate['field'])->map(fn ($value): string => $this->municipalityCode($value))->filter()->values();
            if ($codes->isEmpty()) {
                continue;
            }
            $official = collect($this->boundaries->handle()['features'] ?? [])->map(fn (array $feature): string => $this->municipalityCode(data_get($feature, 'properties.mpio_cdpmp')))->filter()->unique();
            $matched = $codes->filter(fn (string $code): bool => $official->contains($code));
            if ($matched->count() / max(1, $codes->count()) >= .8) {
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
