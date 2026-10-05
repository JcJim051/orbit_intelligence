<?php

namespace App\Services\Intelligence;

use App\Models\MetaProducto;
use App\Models\PlanIndicativoMeta;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlanIndicativoWorkbook
{
    public function __construct(private readonly CatalogWorkbook $workbook) {}

    public function writeTemplate(string $path, int $vigencia): void
    {
        $headers = [
            'vigencia',
            'codigo_meta_producto',
            'meta_producto',
            'subprograma',
            'dependencia',
            'valor_programado',
            'observacion',
        ];

        $rows = [];

        MetaProducto::query()
            ->withoutGlobalScopes()
            ->with([
                'subprograma',
                'dependencia',
                'planIndicativo' => fn ($query) => $query->where('vigencia', $vigencia),
            ])
            ->orderBy('codigo')
            ->chunk(250, function (Collection $metas) use (&$rows, $vigencia): void {
                foreach ($metas as $meta) {
                    $plan = $meta->planIndicativo->first();
                    $rows[] = [
                        $vigencia,
                        $meta->codigo,
                        $meta->nombre,
                        $meta->subprograma?->codigo.' — '.$meta->subprograma?->nombre,
                        $meta->dependencia?->etiqueta() ?? '',
                        $plan?->valor_programado ?? 0,
                        $plan?->observacion ?? '',
                    ];
                }
            });

        $this->workbook->write(
            $path,
            'Plan indicativo',
            $headers,
            $rows,
            [
                'No cambie los nombres de las columnas.',
                'Para actualizar una vigencia existente, conserve la vigencia y edite valor_programado.',
                'Para crear la vigencia siguiente, cambie la columna vigencia o descargue una plantilla para esa vigencia.',
                'codigo_meta_producto debe existir en SIID. La importación actualiza si ya existe meta+vigencia y crea si no existe.',
                'valor_programado es el valor físico programado de la meta producto para la vigencia.',
            ],
        );
    }

    /**
     * @return array{leidas: int, creadas: int, actualizadas: int, sin_cambios: int, errores: list<string>}
     */
    public function import(string $path, ?int $vigenciaDefault, User $user): array
    {
        $table = $this->workbook->readTable($path);
        $headers = array_map(fn (string $header): string => mb_strtolower(trim($header)), $table['headers']);
        $missing = array_values(array_diff(['codigo_meta_producto', 'valor_programado'], $headers));

        if ($missing !== []) {
            return $this->emptyResult(['Faltan columnas obligatorias: '.implode(', ', $missing).'.']);
        }

        $result = $this->emptyResult();

        DB::transaction(function () use ($table, $vigenciaDefault, $user, &$result): void {
            foreach ($table['rows'] as $row) {
                $line = $row['line'];
                $values = $this->normalizeKeys($row['values']);
                $codigo = $this->cleanDigits($values['codigo_meta_producto'] ?? null);
                $vigencia = $this->integer($values['vigencia'] ?? null) ?: $vigenciaDefault;
                $valor = $this->number($values['valor_programado'] ?? null);
                $observacion = $this->cleanText($values['observacion'] ?? null);

                if ($codigo === '' && $valor === null && $observacion === '') {
                    continue;
                }

                $result['leidas']++;

                if (! $vigencia || $vigencia < 2024 || $vigencia > 2035) {
                    $result['errores'][] = "Fila {$line}: vigencia inválida.";

                    continue;
                }

                if (! preg_match('/^\d{6,20}$/', $codigo)) {
                    $result['errores'][] = "Fila {$line}: código de meta producto vacío o inválido.";

                    continue;
                }

                if ($valor === null || $valor < 0) {
                    $result['errores'][] = "Fila {$line}: valor_programado debe ser numérico y mayor o igual a cero.";

                    continue;
                }

                $meta = MetaProducto::query()->withoutGlobalScopes()->where('codigo', $codigo)->first();

                if (! $meta) {
                    $result['errores'][] = "Fila {$line}: la meta producto {$codigo} no existe.";

                    continue;
                }

                $plan = PlanIndicativoMeta::query()->firstOrNew([
                    'meta_producto_id' => $meta->id,
                    'vigencia' => $vigencia,
                ]);
                $exists = $plan->exists;
                $before = [
                    'valor_programado' => (float) ($plan->valor_programado ?? 0),
                    'observacion' => (string) ($plan->observacion ?? ''),
                ];

                $plan->fill([
                    'valor_programado' => $valor,
                    'observacion' => $observacion !== '' ? $observacion : null,
                    'updated_by' => $user->id,
                ])->save();

                if (! $exists) {
                    $result['creadas']++;
                } elseif ($before['valor_programado'] !== (float) $valor || $before['observacion'] !== (string) ($plan->observacion ?? '')) {
                    $result['actualizadas']++;
                } else {
                    $result['sin_cambios']++;
                }
            }
        });

        if ($result['leidas'] === 0) {
            $result['errores'][] = 'El archivo no contiene filas para importar.';
        }

        return $result;
    }

    /**
     * @param  list<string>  $errors
     * @return array{leidas: int, creadas: int, actualizadas: int, sin_cambios: int, errores: list<string>}
     */
    private function emptyResult(array $errors = []): array
    {
        return [
            'leidas' => 0,
            'creadas' => 0,
            'actualizadas' => 0,
            'sin_cambios' => 0,
            'errores' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeKeys(array $values): array
    {
        return collect($values)
            ->mapWithKeys(fn (mixed $value, string $key): array => [mb_strtolower(trim($key)) => $value])
            ->all();
    }

    private function cleanDigits(mixed $value): string
    {
        return preg_replace('/\D+/', '', trim((string) $value)) ?? '';
    }

    private function cleanText(mixed $value): string
    {
        return trim((string) $value);
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) preg_replace('/\D+/', '', (string) $value);
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(['$', ' ', "\u{00A0}"], '', (string) $value);
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
