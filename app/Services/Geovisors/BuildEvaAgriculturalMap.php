<?php

namespace App\Services\Geovisors;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class BuildEvaAgriculturalMap
{
    public function __construct(
        private readonly Factory $http,
        private readonly GetMetaMunicipalBoundaries $boundaries,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(int $year, string $crop, string $metric): array
    {
        $cacheKey = 'geovisors:eva-meta:'.hash('xxh128', "{$year}|{$crop}|{$metric}");

        return Cache::remember(
            $cacheKey,
            now()->addHours((int) config('eva.cache_hours')),
            fn (): array => $this->build($year, $crop, $metric),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $year, string $crop, string $metric): array
    {
        $metrics = config('eva.metrics');
        $metricDefinition = $metrics[$metric] ?? null;
        if (! is_array($metricDefinition)) {
            throw new RuntimeException('La métrica EVA solicitada no está configurada.');
        }

        $rows = $this->request()->get('resource/'.config('eva.dataset_id').'.json', [
            '$select' => implode(', ', [
                'c_digo_dane_municipio',
                'municipio',
                'sum(rea_sembrada::number) as area_sembrada',
                'sum(rea_cosechada::number) as area_cosechada',
                'sum(producci_n::number) as produccion',
            ]),
            '$where' => sprintf(
                'c_digo_dane_departamento="%s" and a_o="%d" and cultivo="%s"',
                config('eva.department_code'),
                $year,
                str_replace('"', '\\"', $crop),
            ),
            '$group' => 'c_digo_dane_municipio, municipio',
            '$order' => 'c_digo_dane_municipio',
            '$limit' => 100,
        ])->throw()->json();

        $valuesByMunicipality = collect($rows)->mapWithKeys(function (array $row): array {
            $harvestedArea = (float) ($row['area_cosechada'] ?? 0);
            $production = (float) ($row['produccion'] ?? 0);

            return [$this->municipalityCode($row['c_digo_dane_municipio'] ?? null) => [
                'municipio_eva' => $row['municipio'] ?? null,
                'area_sembrada' => round((float) ($row['area_sembrada'] ?? 0), 2),
                'area_cosechada' => round($harvestedArea, 2),
                'produccion' => round($production, 2),
                'rendimiento' => $harvestedArea > 0 ? round($production / $harvestedArea, 2) : null,
            ]];
        });

        $boundaries = $this->boundaries->handle();
        $features = $boundaries['features'] ?? null;
        if (! is_array($features) || $features === []) {
            throw new RuntimeException('La fuente oficial no devolvió geometrías municipales del Meta.');
        }

        $joinedFeatures = collect($features)->map(function (array $feature) use ($valuesByMunicipality, $year, $crop, $metricDefinition): array {
            if (! is_array($feature['geometry'] ?? null)) {
                throw new RuntimeException('Un municipio del Meta no contiene una geometría válida.');
            }

            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $code = $this->municipalityCode($properties['mpio_cdpmp'] ?? null);
            $values = $valuesByMunicipality->get($code, [
                'municipio_eva' => null,
                'area_sembrada' => null,
                'area_cosechada' => null,
                'produccion' => null,
                'rendimiento' => null,
            ]);

            return [
                'type' => 'Feature',
                'properties' => [
                    'codigo_dane' => $code,
                    'municipio' => $properties['mpio_cnmbr'] ?? $values['municipio_eva'],
                    'cultivo' => $crop,
                    'año' => $year,
                    ...$values,
                    'valor' => $values[$metricDefinition['property']],
                    'metrica' => $metricDefinition['label'],
                    'unidad' => $metricDefinition['unit'],
                ],
                'geometry' => $feature['geometry'],
            ];
        })->values()->all();

        return [
            'type' => 'FeatureCollection',
            'metadata' => [
                'source' => 'UPRA · Evaluaciones Agropecuarias Municipales (EVA) 2019–2025',
                'source_url' => config('eva.source_url'),
                'dataset_id' => config('eva.dataset_id'),
                'year' => $year,
                'crop' => $crop,
                'metric' => $metric,
                'metric_label' => $metricDefinition['label'],
                'unit' => $metricDefinition['unit'],
            ],
            'features' => $joinedFeatures,
        ];
    }

    private function request(): PendingRequest
    {
        $request = $this->http
            ->baseUrl(rtrim((string) config('investments.socrata_base_url'), '/'))
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->retry(2, 500, throw: false);

        if ($token = config('investments.socrata_app_token')) {
            $request->withHeaders(['X-App-Token' => $token]);
        }

        return $request;
    }

    private function municipalityCode(mixed $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        return str_pad(substr($digits, -5), 5, '0', STR_PAD_LEFT);
    }
}
