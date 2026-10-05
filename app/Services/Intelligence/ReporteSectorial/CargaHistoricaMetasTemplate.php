<?php

namespace App\Services\Intelligence\ReporteSectorial;

use App\Models\Actividad;
use App\Models\MetaProducto;
use App\Models\Seguimiento;
use App\Models\Techo;
use App\Services\Intelligence\CatalogWorkbook;
use Illuminate\Support\Collection;

class CargaHistoricaMetasTemplate
{
    public function __construct(private readonly CatalogWorkbook $workbook) {}

    public function write(Seguimiento $seguimiento, string $path): void
    {
        $headers = [
            'PILAR DE GOBIERNO',
            'EJE ESTRATEGICO',
            'LINEA ESTRATEGICA',
            'PROGRAMA',
            'SUBPROGRAMA',
            'SECTOR (MGA) - CATALOGO SISPT',
            'META RESULTADO',
            'INDICADOR RESULTADO',
            'COD. META PRODUCTO',
            'META PRODUCTO',
            'LINEA BASE',
            'INDICADOR PRODUCTO',
            'ORIENTACION',
            'ODS',
            'TRAZADOR PRESUPUESTAL',
            'CATEGORIA TRAZADOR',
            'RESPONSABLE',
            'BPIN',
            'NOMBRE DE PROYECTO',
            'ENTIDAD EJECUTORA',
            'FUENTE DE FINANCIACION',
            'ASIGNACION POR FUENTE',
            'COMPROMISOS POR FUENTE',
            'OBLIGADO POR FUENTE',
            '% AVANCE FINANCIERO',
            'PROGRAMACION FISICA',
            'AVANCE FISICO',
            '% AVANCE FISICO',
            'ASIGNADO',
            'COMPROMETIDO',
            'OBLIGADO',
            'OBSERVACIONES',
        ];

        $rows = [];

        $techos = Techo::query()
            ->withoutGlobalScopes()
            ->where('seguimiento_id', $seguimiento->id)
            ->with([
                'dependencia',
                'fuente',
                'proyecto.metasProducto',
                'proyecto.actividades' => fn ($query) => $query->withoutGlobalScopes()->with('metaProducto')->orderBy('codigo'),
            ])
            ->get()
            ->sortBy([
                fn (Techo $a, Techo $b): int => strcmp((string) $b->proyecto?->bpin, (string) $a->proyecto?->bpin),
                fn (Techo $a, Techo $b): int => strcmp((string) $a->dependencia?->codigo, (string) $b->dependencia?->codigo),
                fn (Techo $a, Techo $b): int => strcmp((string) $a->fuente?->codigo, (string) $b->fuente?->codigo),
            ]);

        foreach ($techos as $techo) {
            $metas = $this->metasParaTecho($techo);

            if ($metas->isEmpty()) {
                $rows[] = $this->row($techo, null, null);

                continue;
            }

            foreach ($metas as $metaRow) {
                $rows[] = $this->row($techo, $metaRow['meta'], $metaRow['programacion']);
            }
        }

        $this->workbook->write(
            $path,
            'Avance consolidado',
            $headers,
            $rows,
            [
                'Esta plantilla está prediligenciada desde los BPIN, dependencias, fuentes y metas producto que SIID conoce para el seguimiento.',
                'No cambie los nombres de las columnas. Puede dejar columnas descriptivas sin tocar.',
                'Diligencie AVANCE FISICO, COMPROMISOS POR FUENTE, OBLIGADO POR FUENTE y OBSERVACIONES.',
                'PROGRAMACION FISICA viene de la actividad/meta consolidada; si está vacía, complete el programado físico del mes.',
                'La importación es idempotente: al volver a subir el archivo actualiza la misma meta/proyecto/dependencia/fuente, no duplica.',
                'Las filas sin COD. META PRODUCTO o BPIN quedan bloqueadas en el diagnóstico.',
            ],
        );
    }

    /**
     * @return Collection<int, array{meta: MetaProducto, programacion: float|null}>
     */
    private function metasParaTecho(Techo $techo): Collection
    {
        $proyecto = $techo->proyecto;

        if (! $proyecto) {
            return collect();
        }

        $desdeActividades = $proyecto->actividades
            ->filter(fn (Actividad $actividad): bool => (int) $actividad->dependencia_id === (int) $techo->dependencia_id && $actividad->metaProducto !== null)
            ->groupBy('meta_producto_id')
            ->map(function (Collection $actividades): array {
                /** @var Actividad $actividad */
                $actividad = $actividades->first();

                return [
                    'meta' => $actividad->metaProducto,
                    'programacion' => (float) $actividades->sum(fn (Actividad $item): float => (float) $item->cantidad_programada),
                ];
            })
            ->values();

        if ($desdeActividades->isNotEmpty()) {
            return $desdeActividades;
        }

        return $proyecto->metasProducto
            ->filter(fn (MetaProducto $meta): bool => $meta->dependencia_id === null || (int) $meta->dependencia_id === (int) $techo->dependencia_id)
            ->map(fn (MetaProducto $meta): array => ['meta' => $meta, 'programacion' => null])
            ->values();
    }

    /**
     * @return list<mixed>
     */
    private function row(Techo $techo, ?MetaProducto $meta, ?float $programacion): array
    {
        $proyecto = $techo->proyecto;
        $dependencia = $techo->dependencia;
        $fuente = $techo->fuente;
        $metaResultado = $meta?->metaResultado;
        $subprograma = $meta?->subprograma;

        return [
            '',
            '',
            '',
            '',
            $subprograma?->nombre ?? '',
            $meta?->sectorMga?->nombre ?? '',
            $metaResultado?->codigo ? $metaResultado->codigo.' — '.$metaResultado->nombre : ($metaResultado?->nombre ?? ''),
            $metaResultado?->indicador?->nombre ?? '',
            $meta?->codigo ?? '',
            $meta?->nombre ?? '',
            '',
            '',
            '',
            '',
            '',
            '',
            $dependencia?->nombre ?? '',
            $proyecto?->bpin ?? '',
            $proyecto?->nombre ?? '',
            '',
            $fuente?->etiqueta() ?? '',
            (float) $techo->valor,
            '',
            '',
            '',
            $programacion,
            '',
            '',
            (float) $techo->valor,
            '',
            '',
            '',
        ];
    }
}
