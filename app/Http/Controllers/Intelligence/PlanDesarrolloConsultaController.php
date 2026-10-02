<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\IndicadorResultado;
use App\Models\MetaProducto;
use App\Models\MetaResultado;
use App\Models\PddEje;
use App\Models\PddLinea;
use App\Models\PddPilar;
use App\Models\PddPrograma;
use App\Models\PddSubprograma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class PlanDesarrolloConsultaController extends Controller
{
    public function estructura(): JsonResponse
    {
        $this->authorize('viewAny', PddPilar::class);

        $pilares = PddPilar::query()
            ->with(['ejes.lineas.programas.subprogramas'])
            ->orderBy('codigo')
            ->get();

        return response()->json([
            'pilares' => $pilares->map(fn (PddPilar $pilar): array => [
                ...$this->node($pilar),
                'ejes' => $pilar->ejes->map(fn (PddEje $eje): array => [
                    ...$this->node($eje),
                    'lineas' => $eje->lineas->map(fn (PddLinea $linea): array => [
                        ...$this->node($linea),
                        'programas' => $linea->programas->map(fn (PddPrograma $programa): array => [
                            ...$this->node($programa),
                            'subprogramas' => $programa->subprogramas->map(fn (PddSubprograma $subprograma): array => $this->node($subprograma))->values(),
                        ])->values(),
                    ])->values(),
                ])->values(),
            ])->values(),
        ]);
    }

    public function metaResultado(MetaResultado $metaResultado): JsonResponse
    {
        $this->authorize('view', $metaResultado);

        $metaResultado->load([
            'indicador',
            'programa.linea.eje.pilar',
            'subprograma.programa.linea.eje.pilar',
            'metasProducto.subprograma',
            'metasProducto.sectorMga',
            'metasProducto.dependencia',
        ]);

        $subprograma = $metaResultado->subprograma;
        $programa = $subprograma?->programa ?? $metaResultado->programa;
        $linea = $programa?->linea;
        $eje = $linea?->eje;
        $pilar = $eje?->pilar;

        return response()->json([
            'id' => $metaResultado->id,
            'codigo' => $metaResultado->codigo,
            'codigo_provisional' => $metaResultado->codigo_provisional,
            'descripcion' => $metaResultado->descripcion,
            'linea_base' => $metaResultado->linea_base,
            'meta_cuatrienio' => $metaResultado->meta_cuatrienio,
            'observacion' => $metaResultado->observacion,
            'activo' => $metaResultado->activo,
            'indicador' => $this->indicador($metaResultado->indicador),
            'cadena' => [
                'pilar' => $this->nullableNode($pilar),
                'eje' => $this->nullableNode($eje),
                'linea' => $this->nullableNode($linea),
                'programa' => $this->nullableNode($programa),
                'subprograma' => $this->nullableNode($subprograma),
            ],
            'metas_producto' => $metaResultado->metasProducto
                ->sortBy('codigo')
                ->values()
                ->map(fn (MetaProducto $meta): array => [
                    'id' => $meta->id,
                    'codigo' => $meta->codigo,
                    'nombre' => $meta->nombre,
                    'activo' => $meta->activo,
                    'subprograma' => $this->nullableNode($meta->subprograma),
                    'sector' => $meta->sectorMga === null ? null : [
                        'id' => $meta->sectorMga->id,
                        'codigo' => $meta->sectorMga->codigo,
                        'nombre' => $meta->sectorMga->nombre,
                    ],
                    'dependencia' => $meta->dependencia === null ? null : [
                        'id' => $meta->dependencia->id,
                        'codigo' => $meta->dependencia->codigo,
                        'nombre' => $meta->dependencia->nombre,
                    ],
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nullableNode(?Model $record): ?array
    {
        return $record === null ? null : $this->node($record);
    }

    /**
     * @return array<string, mixed>
     */
    private function node(Model $record): array
    {
        return [
            'id' => $record->getKey(),
            'codigo' => $record->getAttribute('codigo'),
            'numeral' => $record->getAttribute('numeral'),
            'nombre' => $record->getAttribute('nombre'),
            'activo' => $record->getAttribute('activo'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function indicador(?IndicadorResultado $indicador): ?array
    {
        if ($indicador === null) {
            return null;
        }

        return [
            'id' => $indicador->id,
            'codigo' => $indicador->codigo,
            'nombre' => $indicador->nombre,
            'unidad_medida' => $indicador->unidad_medida,
            'orientacion' => $indicador->orientacion?->value,
            'linea_base' => $indicador->linea_base,
            'linea_base_texto' => $indicador->linea_base_texto,
            'meta_cuatrienio' => $indicador->meta_cuatrienio,
            'fuente_verificacion' => $indicador->fuente_verificacion,
        ];
    }
}
