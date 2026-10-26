<?php

namespace App\Services\Intelligence\IndicadoresTerritoriales;

use App\Models\Dependencia;
use App\Models\IndicadorTerritorial;
use App\Services\Intelligence\CatalogWorkbook;
use Illuminate\Support\Str;
use RuntimeException;

class ImportadorMatrizIndicadores
{
    /** @var array<string, string> Nombre de la matriz => código del catálogo SIID. */
    private const DEPENDENCIA_ALIASES = [
        'DIRECCIÓN DEPARTAMENTAL PARA LA GESTIÓN DEL RIESGO DE DESASTRES DEL META - DIGERD' => 'SEC. GESTION DEL RIESGO',
        'AGENCIA DE INFRAESTRUCTURA DEL META AIM' => 'AIM',
        'INSTITUTO DEPARTAMENTAL DE TRANSITO Y TRANSPORTE DEL META' => 'TRANSITO',
        'SECRETARIA TÉCNOLOGIA Y SISTEMAS DE INFORMACIÓN' => 'SEC. TICS',
        'DEPARTAMENTO ADMINISTRATIVO DE PLANEACIÓN DEPARTAMENTAL DAPD' => 'DAPD',
        'DIRECCIÓN PARA EL FOMENTO DE LA EDUCACIÓN SUPERIOR' => 'FSES',
        'EMPRESA DE SERVICIOS PUBLICOS DEL META EDESA SA ESP' => 'EDESA',
        'SECRETARIA DE LA MUJER, LA FAMILIA Y EQUIDAD DE GÉNERO' => 'SEC. MUJER',
        'SECRETARIA DE SALUD DEL META' => 'SEC. SALUD',
    ];

    /** @var array<string, list<int>> */
    private const FILAS_GRUPO_PET = [
        'verde' => [53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 120, 121, 122, 123, 124, 125, 130, 131, 146],
        'amarillo' => [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 111, 145],
        'blanco' => [2, 3, 4, 5, 6, 94, 95, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 112, 113, 114, 115, 116, 117, 118, 119, 126, 127, 128, 129, 132, 133, 134, 135, 136, 137, 138, 139, 140, 141, 142, 143, 144],
    ];

    public function __construct(private readonly CatalogWorkbook $workbook) {}

    /** @return array{created: int, updated: int, skipped: int, total: int, unmatched_dependencies: list<string>} */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el archivo: {$path}");
        }

        $sheets = $this->workbook->namedSheets($path);
        $rows = $sheets['Fichas indicadores'] ?? null;

        if ($rows === null) {
            throw new RuntimeException('El archivo debe contener la hoja "Fichas indicadores".');
        }

        return $this->importRows($rows, basename($path));
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int, total: int, unmatched_dependencies: list<string>}
     */
    public function importRows(array $rows, string $sourceName = 'Matriz consolidada PET'): array
    {
        $headerIndex = collect($rows)->search(fn (array $row): bool => in_array('Código', $row, true) && in_array('Nombre del indicador', $row, true));
        if ($headerIndex === false) {
            throw new RuntimeException('No se encontró la fila de encabezados de las fichas de indicadores.');
        }

        $headers = array_map(fn (mixed $value): string => trim((string) $value), $rows[$headerIndex]);
        $dependencyRecords = Dependencia::query()->get(['id', 'nombre', 'sigla', 'codigo']);
        $dependencies = $dependencyRecords
            ->flatMap(function (Dependencia $dependencia): array {
                $keys = array_filter([
                    $this->normalize($dependencia->nombre),
                    $this->normalize($dependencia->sigla),
                    $this->normalize($dependencia->codigo),
                ]);

                return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $dependencia->id])->all();
            });
        $dependenciesByCode = $dependencyRecords->keyBy(fn (Dependencia $dependencia): string => $this->normalize($dependencia->codigo));

        foreach (self::DEPENDENCIA_ALIASES as $dependencySourceName => $catalogCode) {
            $catalogDependency = $dependenciesByCode->get($this->normalize($catalogCode));
            if ($catalogDependency) {
                $dependencies[$this->normalize($dependencySourceName)] = $catalogDependency->id;
            }
        }

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'total' => 0, 'unmatched_dependencies' => []];

        foreach (array_slice($rows, $headerIndex + 1) as $offset => $row) {
            $values = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $values[$header] = $this->clean($row[$index] ?? null);
                }
            }

            $codigo = (string) ($values['Código'] ?? '');
            $nombre = (string) ($values['Nombre del indicador'] ?? '');
            if ($codigo === '' || $nombre === '') {
                continue;
            }

            $result['total']++;
            $existing = IndicadorTerritorial::query()->where('codigo', $codigo)->first();
            if ($existing && $existing->estado !== 'precargado') {
                $result['skipped']++;

                continue;
            }

            $dependenciaFuente = (string) ($values['Dependencia en plataforma'] ?? $values['Entidad productora'] ?? '');
            $dependenciaId = $dependencies[$this->normalize($dependenciaFuente)] ?? null;
            if ($dependenciaFuente !== '' && $dependenciaId === null) {
                $result['unmatched_dependencies'][] = $dependenciaFuente;
            }

            $attributes = [
                'nombre' => $nombre,
                'definicion' => $values['Definición'] ?? null,
                'objetivo' => $values['Objetivo'] ?? null,
                'dependencia_id' => $dependenciaId,
                'dependencia_fuente' => $dependenciaFuente ?: null,
                'area_tematica' => $values['Área temática'] ?? null,
                'tipo_indicador' => $values['Tipo de cálculo propuesto'] ?? null,
                'unidad_medida' => $values['Unidad propuesta'] ?? null,
                'formula_calculo' => $values['Fórmula de cálculo'] ?? null,
                'variables_calculo' => $values['Variables'] ?? null,
                'periodicidad_original' => $values['Periodicidad original'] ?? null,
                'periodicidad' => $this->periodicidad($values['Periodicidad normalizada'] ?? null),
                'fuente_informacion' => $values['Fuente principal'] ?? null,
                'responsable_informacion' => $values['Responsable técnico'] ?? $values['Entidad productora'] ?? null,
                'desagregaciones' => $values['Desagregación temática'] ?? null,
                'cobertura_geografica' => $values['Desagregación geográfica'] ?? null,
                'observaciones_tecnicas' => $values['Alertas de diagnóstico'] ?? null,
                'grupo_asignacion' => $this->grupoAsignacion($values['Filas en PET'] ?? null),
                'fuente_importacion' => $sourceName,
                'fila_fuente' => $this->integer($values['Fila en consolidado'] ?? null) ?? ($headerIndex + $offset + 2),
            ];

            if ($existing) {
                $existing->update($attributes);
                $result['updated']++;
            } else {
                IndicadorTerritorial::query()->create(['codigo' => $codigo, 'estado' => 'precargado', ...$attributes]);
                $result['created']++;
            }
        }

        $result['unmatched_dependencies'] = array_values(array_unique($result['unmatched_dependencies']));

        return $result;
    }

    private function grupoAsignacion(mixed $value): string
    {
        preg_match_all('/\d+/', (string) $value, $matches);
        $rows = array_map('intval', $matches[0] ?? []);

        foreach (self::FILAS_GRUPO_PET as $group => $groupRows) {
            if (array_intersect($rows, $groupRows) !== []) {
                return $group;
            }
        }

        return 'sin_asignar';
    }

    private function periodicidad(mixed $value): ?string
    {
        $normalized = $this->normalize($value);
        if ($normalized === '') {
            return null;
        }

        return match (true) {
            str_contains($normalized, 'diari') => 'diaria',
            str_contains($normalized, 'seman') => 'semanal',
            str_contains($normalized, 'mensual') => 'mensual',
            str_contains($normalized, 'trimestr') => 'trimestral',
            str_contains($normalized, 'cuatrimestr') => 'cuatrimestral',
            str_contains($normalized, 'semestr') => 'semestral',
            str_contains($normalized, 'anual') => 'anual',
            default => 'combinada',
        };
    }

    private function normalize(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    private function clean(mixed $value): mixed
    {
        return is_string($value) ? (trim($value) !== '' ? trim($value) : null) : $value;
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
