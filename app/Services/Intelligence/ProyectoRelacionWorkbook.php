<?php

namespace App\Services\Intelligence;

use App\Models\Actividad;
use App\Models\Dependencia;
use App\Models\MetaProducto;
use App\Models\Proyecto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProyectoRelacionWorkbook
{
    public function __construct(private readonly CatalogWorkbook $workbook) {}

    public function writeTemplate(string $path): void
    {
        $headers = [
            'bpin',
            'nombre_proyecto',
            'dependencia_codigo',
            'dependencia_nombre',
            'responsable_principal',
            'codigo_meta_producto',
            'meta_producto',
            'unidad_medida',
            'cantidad_programada',
            'observacion',
        ];

        $rows = [];

        Proyecto::query()
            ->withoutGlobalScopes()
            ->with([
                'dependencias' => fn ($query) => $query->orderBy('codigo'),
                'metasProducto' => fn ($query) => $query->orderBy('codigo'),
                'actividades' => fn ($query) => $query->withoutGlobalScopes()->with(['dependencia', 'metaProducto'])->orderBy('codigo')->orderBy('nombre'),
            ])
            ->orderByRaw("regexp_replace(bpin, '\\D', '', 'g')::numeric desc")
            ->orderByDesc('id')
            ->chunk(250, function (Collection $proyectos) use (&$rows): void {
                foreach ($proyectos as $proyecto) {
                    $projectRows = $this->rowsForProject($proyecto);
                    array_push($rows, ...$projectRows);
                }
            });

        $this->workbook->write(
            $path,
            'Relaciones',
            $headers,
            $rows,
            [
                'Esta matriz se trabaja desde BPIN/proyecto. No cambie los nombres de las columnas.',
                'Complete dependencia_codigo para vincular el proyecto con una dependencia. Use el código o la sigla tal como aparece en SIID.',
                'Complete codigo_meta_producto para vincular el proyecto con una meta producto existente.',
                'Cada fila con BPIN, dependencia y meta producto crea o actualiza una única actividad técnica consolidada para esa meta.',
                'cantidad_programada corresponde al programado físico de esa meta producto en ese proyecto y dependencia.',
                'responsable_principal acepta SI, NO, 1, 0, verdadero o falso. Si se deja vacío se conserva o se crea como NO.',
                'La importación no borra relaciones existentes: crea o actualiza sin duplicar ni crear actividades adicionales.',
            ],
        );
    }

    /**
     * @return array{leidas: int, dependencias_vinculadas: int, metas_vinculadas: int, actividades_creadas: int, actividades_actualizadas: int, proyectos_creados: int, existentes: int, errores: list<string>}
     */
    public function import(string $path): array
    {
        $table = $this->workbook->readTable($path);
        $headers = array_map(fn (string $header): string => mb_strtolower(trim($header)), $table['headers']);
        $required = ['bpin'];
        $missing = array_values(array_diff($required, $headers));

        if ($missing !== []) {
            return $this->emptyResult(['Faltan columnas obligatorias: '.implode(', ', $missing).'.']);
        }

        $result = $this->emptyResult();

        DB::transaction(function () use ($table, &$result): void {
            foreach ($table['rows'] as $row) {
                $line = $row['line'];
                $values = $this->normalizeKeys($row['values']);
                $bpin = $this->cleanDigits($values['bpin'] ?? null);
                $nombreProyecto = trim((string) ($values['nombre_proyecto'] ?? ''));
                $dependenciaToken = $this->token($values['dependencia_codigo'] ?? null);
                $codigoMeta = $this->cleanDigits($values['codigo_meta_producto'] ?? null);
                $unidadMedida = $this->cleanText($values['unidad_medida'] ?? null) ?: 'Número';
                $cantidadProgramada = $this->number($values['cantidad_programada'] ?? null);
                $principal = $this->boolean($values['responsable_principal'] ?? null);

                if ($bpin === '' && $dependenciaToken === '' && $codigoMeta === '') {
                    continue;
                }

                $result['leidas']++;

                if (! preg_match('/^\d{6,20}$/', $bpin)) {
                    $result['errores'][] = "Fila {$line}: BPIN vacío o inválido.";

                    continue;
                }

                $proyecto = Proyecto::query()->withoutGlobalScopes()->where('bpin', $bpin)->first();

                if (! $proyecto && $nombreProyecto === '') {
                    $result['errores'][] = "Fila {$line}: el BPIN {$bpin} no existe y no trae nombre_proyecto para crearlo.";

                    continue;
                }

                if (! $proyecto) {
                    $proyecto = Proyecto::query()->withoutGlobalScopes()->create([
                        'bpin' => $bpin,
                        'nombre' => $nombreProyecto,
                        'activo' => true,
                    ]);
                    $result['proyectos_creados']++;
                } elseif ($nombreProyecto !== '' && $proyecto->nombre !== $nombreProyecto) {
                    $proyecto->forceFill(['nombre' => $nombreProyecto])->save();
                }

                $dependencia = null;
                if ($dependenciaToken !== '') {
                    $dependencia = $this->findDependencia($dependenciaToken);

                    if (! $dependencia) {
                        $result['errores'][] = "Fila {$line}: la dependencia {$dependenciaToken} no existe.";

                        continue;
                    }

                    $alreadyLinked = $proyecto->dependencias()->whereKey($dependencia->id)->exists();
                    $pivotAttributes = ['origen' => 'matriz-proyectos'];

                    if ($principal !== null) {
                        $pivotAttributes['es_responsable_principal'] = $principal;
                    } elseif (! $alreadyLinked) {
                        $pivotAttributes['es_responsable_principal'] = false;
                    }

                    $proyecto->dependencias()->syncWithoutDetaching([$dependencia->id => $pivotAttributes]);
                    $alreadyLinked ? $result['existentes']++ : $result['dependencias_vinculadas']++;
                }

                $meta = null;
                if ($codigoMeta !== '') {
                    if (! preg_match('/^\d{6,20}$/', $codigoMeta)) {
                        $result['errores'][] = "Fila {$line}: código de meta producto inválido.";

                        continue;
                    }

                    $meta = MetaProducto::query()->withoutGlobalScopes()->where('codigo', $codigoMeta)->first();

                    if (! $meta) {
                        $result['errores'][] = "Fila {$line}: la meta producto {$codigoMeta} no existe.";

                        continue;
                    }

                    $alreadyLinked = $proyecto->metasProducto()->withoutGlobalScopes()->whereKey($meta->id)->exists();
                    $proyecto->metasProducto()->syncWithoutDetaching([$meta->id]);
                    $alreadyLinked ? $result['existentes']++ : $result['metas_vinculadas']++;

                    if (! $dependencia && $meta->dependencia_id) {
                        $dependencia = $meta->dependencia;
                        $proyecto->dependencias()->syncWithoutDetaching([
                            $meta->dependencia_id => [
                                'es_responsable_principal' => false,
                                'origen' => 'meta-producto',
                            ],
                        ]);
                    }
                }

                if ($meta) {
                    if (! $dependencia) {
                        $result['errores'][] = "Fila {$line}: para vincular la meta producto debe diligenciar dependencia_codigo o usar una meta producto con dependencia.";

                        continue;
                    }

                    $actividadCodigo = 'META-'.$meta->codigo;
                    $actividadNombre = 'Avance consolidado de la meta producto';
                    $actividad = Actividad::query()
                        ->withoutGlobalScopes()
                        ->where('proyecto_id', $proyecto->id)
                        ->where('dependencia_id', $dependencia->id)
                        ->where('meta_producto_id', $meta->id)
                        ->where('origen', Actividad::ORIGEN_CONSOLIDADO_META)
                        ->first();
                    $exists = (bool) $actividad;
                    $actividad ??= new Actividad;
                    $actividad->fill([
                        'proyecto_id' => $proyecto->id,
                        'dependencia_id' => $dependencia->id,
                        'meta_producto_id' => $meta->id,
                        'codigo' => $actividadCodigo,
                        'nombre' => $actividadNombre,
                        'unidad_medida' => $unidadMedida,
                        'cantidad_programada' => $cantidadProgramada,
                        'origen' => Actividad::ORIGEN_CONSOLIDADO_META,
                        'activo' => true,
                    ])->save();

                    $exists ? $result['actividades_actualizadas']++ : $result['actividades_creadas']++;
                }
            }
        });

        if ($result['leidas'] === 0) {
            $result['errores'][] = 'El archivo no contiene filas con BPIN o relaciones para importar.';
        }

        return $result;
    }

    /**
     * @return list<list<mixed>>
     */
    private function rowsForProject(Proyecto $proyecto): array
    {
        if ($proyecto->metasProducto->isNotEmpty()) {
            $dependencias = $proyecto->dependencias->isNotEmpty() ? $proyecto->dependencias : collect([null]);

            return $proyecto->metasProducto
                ->flatMap(fn (MetaProducto $meta): Collection => $dependencias->map(fn (?Dependencia $dependencia): array => $this->row(
                    $proyecto,
                    $dependencia,
                    $meta,
                    $this->actividadConsolidada($proyecto, $dependencia, $meta),
                )))
                ->values()
                ->all();
        }

        if ($proyecto->actividades->isNotEmpty()) {
            return $proyecto->actividades
                ->filter(fn (Actividad $actividad): bool => $actividad->meta_producto_id !== null)
                ->unique(fn (Actividad $actividad): string => $actividad->dependencia_id.'-'.$actividad->meta_producto_id)
                ->map(fn (Actividad $actividad): array => $this->row(
                    $proyecto,
                    $actividad->dependencia,
                    $actividad->metaProducto,
                    $this->actividadConsolidada($proyecto, $actividad->dependencia, $actividad->metaProducto) ?? $actividad,
                ))
                ->values()
                ->all();
        }

        if ($proyecto->dependencias->isNotEmpty()) {
            return $proyecto->dependencias
                ->map(fn (Dependencia $dependencia): array => $this->row($proyecto, $dependencia))
                ->all();
        }

        return [$this->row($proyecto)];
    }

    /**
     * @return list<mixed>
     */
    private function row(Proyecto $proyecto, ?Dependencia $dependencia = null, ?MetaProducto $meta = null, ?Actividad $actividad = null): array
    {
        return [
            $proyecto->bpin,
            $proyecto->nombre,
            $dependencia?->codigo ?? '',
            $dependencia?->nombre ?? '',
            $dependencia && (bool) ($dependencia->pivot?->es_responsable_principal ?? false) ? 'SI' : 'NO',
            $meta?->codigo ?? '',
            $meta?->nombre ?? '',
            $actividad?->unidad_medida ?? '',
            $actividad?->cantidad_programada ?? '',
            '',
        ];
    }

    private function actividadConsolidada(Proyecto $proyecto, ?Dependencia $dependencia, ?MetaProducto $meta): ?Actividad
    {
        if (! $dependencia || ! $meta) {
            return null;
        }

        return $proyecto->actividades
            ->first(fn (Actividad $actividad): bool => $actividad->dependencia_id === $dependencia->id
                && $actividad->meta_producto_id === $meta->id
                && $actividad->origen === Actividad::ORIGEN_CONSOLIDADO_META)
            ?? $proyecto->actividades->first(fn (Actividad $actividad): bool => $actividad->dependencia_id === $dependencia->id
                && $actividad->meta_producto_id === $meta->id);
    }

    /**
     * @param  list<string>  $errores
     * @return array{leidas: int, dependencias_vinculadas: int, metas_vinculadas: int, actividades_creadas: int, actividades_actualizadas: int, proyectos_creados: int, existentes: int, errores: list<string>}
     */
    private function emptyResult(array $errores = []): array
    {
        return [
            'leidas' => 0,
            'dependencias_vinculadas' => 0,
            'metas_vinculadas' => 0,
            'actividades_creadas' => 0,
            'actividades_actualizadas' => 0,
            'proyectos_creados' => 0,
            'existentes' => 0,
            'errores' => $errores,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeKeys(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $normalized[mb_strtolower(trim((string) $key))] = $value;
        }

        return $normalized;
    }

    private function findDependencia(string $token): ?Dependencia
    {
        return Dependencia::query()
            ->withoutGlobalScopes()
            ->whereRaw('LOWER(codigo) = ?', [mb_strtolower($token)])
            ->orWhereRaw('LOWER(sigla) = ?', [mb_strtolower($token)])
            ->orWhere('id', ctype_digit($token) ? (int) $token : 0)
            ->first();
    }

    private function token(mixed $value): string
    {
        $value = trim((string) $value);
        $value = preg_split('/\s+[—-]\s+/', $value, 2)[0] ?? $value;

        return trim($value);
    }

    private function cleanDigits(mixed $value): string
    {
        if (is_float($value)) {
            $value = sprintf('%.0f', $value);
        }

        return preg_replace('/\D+/', '', trim((string) $value)) ?? '';
    }

    private function cleanText(mixed $value): string
    {
        return trim((string) $value);
    }

    private function number(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(['$', ' ', '.'], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function boolean(mixed $value): ?bool
    {
        $value = mb_strtolower(trim((string) $value));

        return match ($value) {
            'si', 'sí', 's', '1', 'true', 'verdadero', 'principal' => true,
            'no', 'n', '0', 'false', 'falso' => false,
            default => null,
        };
    }
}
