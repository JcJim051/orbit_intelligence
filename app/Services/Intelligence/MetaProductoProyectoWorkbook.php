<?php

namespace App\Services\Intelligence;

use App\Models\MetaProducto;
use App\Models\Proyecto;
use Illuminate\Support\Collection;

class MetaProductoProyectoWorkbook
{
    public function __construct(private readonly CatalogWorkbook $workbook) {}

    public function writeTemplate(string $path): void
    {
        $headers = [
            'codigo_meta_producto',
            'meta_producto',
            'sector_mga',
            'dependencia',
            'bpin',
            'nombre_proyecto',
            'observacion',
        ];

        $rows = [];

        MetaProducto::query()
            ->withoutGlobalScopes()
            ->with(['sectorMga', 'dependencia', 'proyectos' => fn ($query) => $query->withoutGlobalScopes()->orderBy('bpin')])
            ->orderBy('codigo')
            ->chunk(300, function (Collection $metas) use (&$rows): void {
                foreach ($metas as $meta) {
                    if ($meta->proyectos->isEmpty()) {
                        $rows[] = $this->row($meta, null);

                        continue;
                    }

                    foreach ($meta->proyectos as $proyecto) {
                        $rows[] = $this->row($meta, $proyecto);
                    }
                }
            });

        $this->workbook->write(
            $path,
            'Relaciones',
            $headers,
            $rows,
            [
                'Diligencie o corrija únicamente las columnas bpin y nombre_proyecto.',
                'codigo_meta_producto debe existir en SIID. No cambie el nombre de las columnas.',
                'Si el BPIN ya existe, nombre_proyecto es informativo.',
                'Si el BPIN no existe, nombre_proyecto será usado para crearlo.',
                'La importación vincula sin borrar relaciones existentes.',
            ],
        );
    }

    /**
     * @return array{leidas: int, vinculadas: int, existentes: int, proyectos_creados: int, errores: list<string>}
     */
    public function import(string $path): array
    {
        $table = $this->workbook->readTable($path);
        $headers = array_map(fn (string $header): string => mb_strtolower(trim($header)), $table['headers']);
        $required = ['codigo_meta_producto', 'bpin'];
        $missing = array_values(array_diff($required, $headers));

        if ($missing !== []) {
            return [
                'leidas' => 0,
                'vinculadas' => 0,
                'existentes' => 0,
                'proyectos_creados' => 0,
                'errores' => ['Faltan columnas obligatorias: '.implode(', ', $missing).'.'],
            ];
        }

        $leidas = 0;
        $vinculadas = 0;
        $existentes = 0;
        $proyectosCreados = 0;
        $errores = [];

        foreach ($table['rows'] as $row) {
            $line = $row['line'];
            $values = $this->normalizeKeys($row['values']);
            $codigoMeta = $this->cleanCode($values['codigo_meta_producto'] ?? null);
            $bpin = $this->cleanCode($values['bpin'] ?? null);
            $nombreProyecto = trim((string) ($values['nombre_proyecto'] ?? ''));

            if ($codigoMeta === '' && $bpin === '') {
                continue;
            }

            $leidas++;

            if (! preg_match('/^\d{11}$/', $codigoMeta)) {
                $errores[] = "Fila {$line}: código de meta producto inválido.";

                continue;
            }

            if (! preg_match('/^\d{6,20}$/', $bpin)) {
                $errores[] = "Fila {$line}: BPIN vacío o inválido.";

                continue;
            }

            $meta = MetaProducto::query()->withoutGlobalScopes()->where('codigo', $codigoMeta)->first();

            if (! $meta) {
                $errores[] = "Fila {$line}: la meta producto {$codigoMeta} no existe.";

                continue;
            }

            $proyecto = Proyecto::query()->withoutGlobalScopes()->where('bpin', $bpin)->first();

            if (! $proyecto && $nombreProyecto === '') {
                $errores[] = "Fila {$line}: el BPIN {$bpin} no existe y no trae nombre_proyecto para crearlo.";

                continue;
            }

            if (! $proyecto) {
                $proyecto = Proyecto::query()->withoutGlobalScopes()->create([
                    'bpin' => $bpin,
                    'nombre' => $nombreProyecto,
                    'activo' => true,
                ]);
                $proyectosCreados++;
            }

            if ($meta->dependencia_id) {
                $proyecto->dependencias()->syncWithoutDetaching([
                    $meta->dependencia_id => [
                        'es_responsable_principal' => false,
                        'origen' => 'meta-producto',
                    ],
                ]);
            }

            $alreadyLinked = $meta->proyectos()->withoutGlobalScopes()->whereKey($proyecto->id)->exists();
            $meta->proyectos()->syncWithoutDetaching([$proyecto->id]);

            if ($alreadyLinked) {
                $existentes++;
            } else {
                $vinculadas++;
            }
        }

        if ($leidas === 0) {
            $errores[] = 'El archivo no contiene filas con código de meta producto y BPIN.';
        }

        return [
            'leidas' => $leidas,
            'vinculadas' => $vinculadas,
            'existentes' => $existentes,
            'proyectos_creados' => $proyectosCreados,
            'errores' => $errores,
        ];
    }

    /**
     * @return list<mixed>
     */
    private function row(MetaProducto $meta, ?Proyecto $proyecto): array
    {
        return [
            $meta->codigo,
            $meta->nombre,
            $meta->sectorMga ? $meta->sectorMga->codigo.' — '.$meta->sectorMga->nombre : '',
            $meta->dependencia ? $meta->dependencia->codigo.' — '.$meta->dependencia->nombre : '',
            $proyecto?->bpin ?? '',
            $proyecto?->nombre ?? '',
            '',
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

    private function cleanCode(mixed $value): string
    {
        if (is_float($value)) {
            $value = sprintf('%.0f', $value);
        }

        return preg_replace('/\D+/', '', trim((string) $value)) ?? '';
    }
}
