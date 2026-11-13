<?php

namespace App\Services\Intelligence\IndicadoresTerritoriales;

use App\Models\IndicadorTerritorial;
use App\Models\IndicadorTerritorialDato;
use App\Models\SiidTerritorio;
use App\Models\User;
use App\Services\Intelligence\CatalogWorkbook;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ImportadorHistoricoIndicador
{
    private const REQUIRED_HEADERS = ['CODIGO_INDICADOR', 'PERIODO', 'VALOR'];

    public function __construct(private readonly CatalogWorkbook $workbook) {}

    /** @return array{created:int,updated:int,total:int} */
    public function import(IndicadorTerritorial $indicador, string $path, User $user, string $originalName): array
    {
        $table = $this->workbook->readTable($path);
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $table['headers']));
        if ($missing !== []) {
            throw new RuntimeException('Faltan columnas obligatorias: '.implode(', ', $missing).'.');
        }

        $prepared = [];
        $errors = [];

        foreach ($table['rows'] as $row) {
            $values = $row['values'];
            if ($this->blankRow($values)) {
                continue;
            }

            $code = trim((string) ($values['CODIGO_INDICADOR'] ?? ''));
            $period = trim((string) ($values['PERIODO'] ?? ''));
            $rawValue = $values['VALOR'] ?? null;
            $territory = trim((string) ($values['TERRITORIO_CODIGO'] ?? '50')) ?: '50';
            $breakdownType = trim((string) ($values['DESAGREGACION_TIPO'] ?? 'Total')) ?: 'Total';
            $breakdownValue = trim((string) ($values['DESAGREGACION_VALOR'] ?? 'Total')) ?: 'Total';

            if ($code !== $indicador->codigo) {
                $errors[] = "Fila {$row['line']}: el código debe ser {$indicador->codigo}.";
            }
            if ($period === '') {
                $errors[] = "Fila {$row['line']}: falta el periodo.";
            }
            if (! is_numeric($rawValue)) {
                $errors[] = "Fila {$row['line']}: VALOR debe ser numérico.";
            }
            if (DB::getDriverName() === 'pgsql' && ! SiidTerritorio::query()->whereKey($territory)->where('activo', true)->exists()) {
                $errors[] = "Fila {$row['line']}: el territorio {$territory} no existe en el catálogo DIVIPOLA.";
            }
            if (str_contains(mb_strtolower((string) $indicador->unidad_medida), 'porcentaje') && is_numeric($rawValue) && ((float) $rawValue < 0 || (float) $rawValue > 100)) {
                $errors[] = "Fila {$row['line']}: el porcentaje debe estar entre 0 y 100.";
            }

            $start = $this->date($values['FECHA_INICIO'] ?? null, $row['line'], 'FECHA_INICIO', $errors);
            $end = $this->date($values['FECHA_FIN'] ?? null, $row['line'], 'FECHA_FIN', $errors);
            $cutoff = $this->date($values['FECHA_CORTE'] ?? null, $row['line'], 'FECHA_CORTE', $errors);

            if ($start && $end && $end->lt($start)) {
                $errors[] = "Fila {$row['line']}: FECHA_FIN no puede ser anterior a FECHA_INICIO.";
            }

            $key = implode('|', [$territory, $period, $breakdownType, $breakdownValue]);
            if ($period !== '' && isset($prepared[$key])) {
                $errors[] = "Fila {$row['line']}: la combinación {$territory}, {$period}, {$breakdownType}, {$breakdownValue} está duplicada en el archivo.";
            }

            $prepared[$key] = [
                'territorio_codigo' => $territory,
                'desagregacion_tipo' => $breakdownType,
                'desagregacion_valor' => $breakdownValue,
                'periodo' => $period,
                'periodo_inicio' => $start?->toDateString(),
                'periodo_fin' => $end?->toDateString(),
                'valor' => is_numeric($rawValue) ? $rawValue : null,
                'numerador' => is_numeric($values['NUMERADOR'] ?? null) ? $values['NUMERADOR'] : null,
                'denominador' => is_numeric($values['DENOMINADOR'] ?? null) ? $values['DENOMINADOR'] : null,
                'es_preliminar' => in_array(mb_strtolower(trim((string) ($values['PRELIMINAR'] ?? ''))), ['si', 'sí', '1', 'true', 'x'], true),
                'fecha_corte' => $cutoff?->toDateString(),
                'fuente' => $this->text($values['FUENTE'] ?? null),
                'enlace_evidencia' => $this->text($values['ENLACE_EVIDENCIA'] ?? null),
                'observacion' => $this->text($values['OBSERVACION'] ?? null),
                'fila_fuente' => $row['line'],
            ];
        }

        if ($prepared === []) {
            $errors[] = 'La plantilla no contiene filas históricas para importar.';
        }

        $approvedPeriods = $indicador->datos()->whereIn('periodo', collect($prepared)->pluck('periodo')->unique())->where('estado', 'aprobado')->pluck('periodo')->all();
        foreach ($approvedPeriods as $period) {
            $errors[] = "El periodo {$period} ya está aprobado y no puede sobrescribirse con una carga masiva.";
        }

        if ($errors !== []) {
            throw new RuntimeException(implode(' ', array_slice(array_unique($errors), 0, 12)));
        }

        return DB::transaction(function () use ($indicador, $prepared, $user, $originalName): array {
            $result = ['created' => 0, 'updated' => 0, 'total' => count($prepared)];

            foreach ($prepared as $row) {
                $datum = IndicadorTerritorialDato::query()->firstOrNew([
                    'indicador_territorial_id' => $indicador->id,
                    'territorio_codigo' => $row['territorio_codigo'],
                    'periodo' => $row['periodo'],
                    'desagregacion_tipo' => $row['desagregacion_tipo'],
                    'desagregacion_valor' => $row['desagregacion_valor'],
                ]);
                $wasRecent = $datum->exists;
                $datum->fill([
                    ...$row,
                    'estado' => 'pendiente',
                    'origen' => 'carga_historica',
                    'archivo_fuente' => $originalName,
                    'reportado_por' => $user->id,
                    'validado_por' => null,
                    'validado_at' => null,
                ])->save();
                $result[$wasRecent ? 'updated' : 'created']++;
            }

            if (in_array($indicador->estado, ['pendiente_historico', 'historico_cargado', 'historico_observado'], true)) {
                $indicador->update(['estado' => 'historico_cargado']);
            }

            return $result;
        });
    }

    /** @param array<string, mixed> $values */
    private function blankRow(array $values): bool
    {
        return collect($values)->every(fn (mixed $value): bool => $value === null || trim((string) $value) === '');
    }

    /** @param list<string> $errors */
    private function date(mixed $value, int $line, string $field, array &$errors): ?CarbonImmutable
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            $raw = trim((string) $value);
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $raw);
            $errorsInfo = CarbonImmutable::getLastErrors();
            if ($date === false || ($errorsInfo !== false && ($errorsInfo['warning_count'] > 0 || $errorsInfo['error_count'] > 0)) || $date->format('Y-m-d') !== $raw) {
                throw new RuntimeException('Fecha inválida');
            }

            return $date;
        } catch (Throwable) {
            $errors[] = "Fila {$line}: {$field} debe usar AAAA-MM-DD.";

            return null;
        }
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
