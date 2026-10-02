<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\MetaProducto;
use App\Models\MetaResultado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Arma el detalle (JSON) de los conteos del listado de catálogos.
 *
 * Cada método recibe la MISMA relación que alimenta withCount() en el
 * listado, de modo que el total del detalle coincide con el número visible
 * (las filas eliminadas lógicamente quedan fuera por el scope de SoftDeletes).
 *
 * Forma: titulo, registro{codigo,nombre}, total, resumen[{label,valor}],
 * grupos[{titulo, nota?, vacio?, items[{codigo, nombre, extra[{label,valor}]}]}].
 */
final class CountDetail
{
    /**
     * @param  list<array{label: string, valor: int}>  $resumen
     * @param  list<array<string, mixed>>  $grupos
     * @return array<string, mixed>
     */
    public static function payload(string $titulo, Model $owner, int $total, array $resumen, array $grupos): array
    {
        return [
            'titulo' => $titulo,
            'registro' => self::registro($owner),
            'total' => $total,
            'resumen' => $resumen,
            'grupos' => $grupos,
        ];
    }

    /**
     * @return array{codigo: ?string, nombre: ?string}
     */
    public static function registro(Model $record): array
    {
        if (PlanLabel::supports($record)) {
            return ['codigo' => PlanLabel::reference($record), 'nombre' => (string) $record->getAttribute('nombre')];
        }

        if ($record instanceof MetaResultado) {
            return ['codigo' => $record->codigo_provisional, 'nombre' => $record->descripcion];
        }

        return [
            'codigo' => $record->getAttribute('codigo') === null ? null : (string) $record->getAttribute('codigo'),
            'nombre' => $record->getAttribute('nombre') === null ? null : (string) $record->getAttribute('nombre'),
        ];
    }

    /**
     * @return array{label: string, valor: int}
     */
    public static function summary(int $count, string $singular, string $plural): array
    {
        return ['label' => $count === 1 ? $singular : $plural, 'valor' => $count];
    }

    public static function countText(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count === 1 ? $singular : $plural);
    }

    /**
     * Metas de producto agrupadas por subprograma.
     *
     * @param  list<string>  $extras  Datos a mostrar por meta: metaResultado, sectorMga, dependencia.
     * @return array<string, mixed>
     */
    public static function metasProducto(Model $owner, string $titulo, Relation $relation, array $extras = ['metaResultado'], bool $summarizeSubprogramas = true): array
    {
        $metas = $relation
            ->with([
                'subprograma' => fn ($query) => $query->withTrashed(),
                'metaResultado' => fn ($query) => $query->withTrashed(),
                'sectorMga' => fn ($query) => $query->withTrashed(),
                'dependencia' => fn ($query) => $query->withTrashed(),
            ])
            ->orderBy($relation->getRelated()->qualifyColumn('codigo'))
            ->get();

        $grupos = self::groupByPlanNode(
            $metas,
            fn (MetaProducto $meta) => $meta->subprograma_id,
            fn (MetaProducto $meta) => $meta->subprograma,
            'Sin subprograma',
            emptyFirst: false,
            item: fn (MetaProducto $meta): array => self::metaProductoItem($meta, $extras),
            labels: ['meta producto', 'metas producto'],
        );

        $total = $metas->count();
        $resumen = [self::summary($total, 'meta producto', 'metas producto')];

        if ($summarizeSubprogramas) {
            $resumen[] = self::summary($metas->pluck('subprograma_id')->filter()->unique()->count(), 'subprograma', 'subprogramas');
        }

        if (in_array('metaResultado', $extras, true)) {
            $resumen[] = self::summary($metas->pluck('meta_resultado_id')->filter()->unique()->count(), 'meta resultado distinta', 'metas resultado distintas');
        }

        return self::payload($titulo, $owner, $total, $resumen, $grupos);
    }

    /**
     * Metas de resultado, agrupadas por subprograma, por programa o en un solo grupo.
     *
     * @param  'subprograma'|'programa'|null  $groupBy
     * @return array<string, mixed>
     */
    public static function metasResultado(Model $owner, string $titulo, Relation $relation, ?string $groupBy, string $emptyGroupTitle, bool $showIndicador = true): array
    {
        $metas = $relation
            ->with([
                'indicador' => fn ($query) => $query->withTrashed(),
                'programa' => fn ($query) => $query->withTrashed(),
                'subprograma' => fn ($query) => $query->withTrashed(),
            ])
            ->withCount('metasProducto')
            ->get()
            ->sortBy(fn (MetaResultado $meta): string => (string) $meta->codigo_provisional, SORT_NATURAL)
            ->values();

        // El subprograma solo se repite por meta cuando no es ya el encabezado ni el registro consultado.
        $item = fn (MetaResultado $meta): array => self::metaResultadoItem($meta, showSubprograma: $groupBy === 'programa', showIndicador: $showIndicador);

        if ($groupBy === null) {
            $grupos = $metas->isEmpty() ? [] : [[
                'titulo' => $emptyGroupTitle,
                'nota' => self::countText($metas->count(), 'meta resultado', 'metas resultado'),
                'items' => $metas->map($item)->values()->all(),
            ]];
        } else {
            $grupos = self::groupByPlanNode(
                $metas,
                fn (MetaResultado $meta) => $meta->{$groupBy.'_id'},
                fn (MetaResultado $meta) => $meta->{$groupBy},
                $emptyGroupTitle,
                emptyFirst: true,
                item: $item,
                labels: ['meta resultado', 'metas resultado'],
            );
        }

        $total = $metas->count();
        $resumen = [self::summary($total, 'meta resultado', 'metas resultado')];

        if ($showIndicador) {
            $resumen[] = self::summary($metas->pluck('indicador_resultado_id')->filter()->unique()->count(), 'indicador distinto', 'indicadores distintos');
        }

        $resumen[] = self::summary((int) $metas->sum('metas_producto_count'), 'meta producto vinculada', 'metas producto vinculadas');

        return self::payload($titulo, $owner, $total, $resumen, $grupos);
    }

    /**
     * Hijos directos de un nodo del plan (grupos) con sus nietos (ítems).
     *
     * @param  array{0: string, 1: string}  $childLabels
     * @param  array{0: string, 1: string}  $grandLabels
     * @param  array<string, array{0: string, 1: string}>  $grandCounts
     * @return array<string, mixed>
     */
    public static function planTree(Model $owner, string $titulo, Relation $relation, array $childLabels, string $grandRelation, array $grandLabels, array $grandCounts = []): array
    {
        $children = $relation
            ->with([$grandRelation => fn ($query) => $query->withCount(array_keys($grandCounts))->reorder()->orderBy('codigo')])
            ->reorder()
            ->orderBy('codigo')
            ->get();

        $grandTotal = 0;
        $countTotals = array_fill_keys(array_keys($grandCounts), 0);

        $grupos = $children->map(function (Model $child) use ($grandRelation, $grandLabels, $grandCounts, &$grandTotal, &$countTotals): array {
            $grand = $child->getRelation($grandRelation);
            $grandTotal += $grand->count();

            foreach (array_keys($grandCounts) as $countRelation) {
                $countTotals[$countRelation] += (int) $grand->sum(self::countAttribute($countRelation));
            }

            return [
                'titulo' => PlanLabel::full($child),
                'nota' => self::countText($grand->count(), $grandLabels[0], $grandLabels[1]),
                'vacio' => 'Sin '.$grandLabels[1].'.',
                'items' => $grand->map(fn (Model $node): array => self::planNodeItem($node, $grandCounts))->values()->all(),
            ];
        })->values()->all();

        $resumen = [
            self::summary($children->count(), $childLabels[0], $childLabels[1]),
            self::summary($grandTotal, $grandLabels[0], $grandLabels[1]),
        ];

        foreach ($grandCounts as $countRelation => [$singular, $plural]) {
            $resumen[] = self::summary($countTotals[$countRelation], $singular, $plural);
        }

        return self::payload($titulo, $owner, $children->count(), $resumen, $grupos);
    }

    /**
     * Hijos directos de un nodo del plan en un solo grupo, con sus conteos.
     *
     * @param  array{0: string, 1: string}  $childLabels
     * @param  array<string, array{0: string, 1: string}>  $childCounts
     * @return array<string, mixed>
     */
    public static function planList(Model $owner, string $titulo, Relation $relation, array $childLabels, array $childCounts = []): array
    {
        $children = $relation
            ->withCount(array_keys($childCounts))
            ->reorder()
            ->orderBy('codigo')
            ->get();

        $resumen = [self::summary($children->count(), $childLabels[0], $childLabels[1])];

        foreach ($childCounts as $countRelation => [$singular, $plural]) {
            $resumen[] = self::summary((int) $children->sum(self::countAttribute($countRelation)), $singular, $plural);
        }

        $grupos = $children->isEmpty() ? [] : [[
            'titulo' => PlanLabel::supports($owner) ? PlanLabel::full($owner) : $titulo,
            'nota' => self::countText($children->count(), $childLabels[0], $childLabels[1]),
            'items' => $children->map(fn (Model $node): array => self::planNodeItem($node, $childCounts))->values()->all(),
        ]];

        return self::payload($titulo, $owner, $children->count(), $resumen, $grupos);
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $counts
     * @return array<string, mixed>
     */
    public static function planNodeItem(Model $node, array $counts = []): array
    {
        $extra = [['label' => 'Código de producción', 'valor' => (string) $node->getAttribute('codigo')]];

        if ($counts !== []) {
            $extra[] = [
                'label' => 'Contiene',
                'valor' => collect($counts)
                    ->map(fn (array $labels, string $relation): string => self::countText((int) $node->getAttribute(self::countAttribute($relation)), $labels[0], $labels[1]))
                    ->implode(' · '),
            ];
        }

        return [
            'codigo' => PlanLabel::reference($node),
            'nombre' => (string) $node->getAttribute('nombre'),
            'extra' => $extra,
        ];
    }

    /**
     * @param  list<string>  $extras
     * @return array<string, mixed>
     */
    public static function metaProductoItem(MetaProducto $meta, array $extras = ['metaResultado']): array
    {
        $extra = [];

        if (in_array('metaResultado', $extras, true)) {
            $extra[] = [
                'label' => 'Meta de resultado',
                'valor' => $meta->metaResultado === null
                    ? 'Sin meta de resultado'
                    : $meta->metaResultado->codigo_provisional.' — '.$meta->metaResultado->descripcion
                        .($meta->metaResultado->trashed() ? ' (eliminada)' : ''),
            ];
        }

        if (in_array('sectorMga', $extras, true)) {
            $extra[] = [
                'label' => 'Sector MGA',
                'valor' => $meta->sectorMga === null ? 'Sin sector' : $meta->sectorMga->codigo.' — '.$meta->sectorMga->nombre,
            ];
        }

        if (in_array('dependencia', $extras, true)) {
            $extra[] = [
                'label' => 'Dependencia',
                'valor' => $meta->dependencia === null ? 'Sin dependencia' : $meta->dependencia->codigo.' — '.$meta->dependencia->nombre,
            ];
        }

        return ['codigo' => $meta->codigo, 'nombre' => $meta->nombre, 'extra' => $extra];
    }

    /**
     * @return array<string, mixed>
     */
    public static function metaResultadoItem(MetaResultado $meta, bool $showSubprograma = true, bool $showIndicador = true): array
    {
        $extra = [];

        if ($meta->codigo !== null && $meta->codigo !== '') {
            $extra[] = ['label' => 'Código oficial', 'valor' => (string) $meta->codigo];
        }

        if ($showIndicador) {
            $extra[] = [
                'label' => 'Indicador',
                'valor' => $meta->indicador === null
                    ? 'Sin indicador'
                    : $meta->indicador->nombre.($meta->indicador->unidad_medida ? ' ('.$meta->indicador->unidad_medida.')' : ''),
            ];
        }

        if ($showSubprograma) {
            $extra[] = [
                'label' => 'Subprograma',
                'valor' => $meta->subprograma === null
                    ? ($meta->programa_id === null ? 'Sin subprograma' : 'Sin subprograma (a nivel de programa)')
                    : PlanLabel::full($meta->subprograma),
            ];
        }

        $extra[] = [
            'label' => 'Metas producto vinculadas',
            'valor' => (string) (int) $meta->getAttribute('metas_producto_count'),
        ];

        return ['codigo' => $meta->codigo_provisional, 'nombre' => $meta->descripcion, 'extra' => $extra];
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return list<array<string, mixed>>
     */
    /**
     * @param  array{0: string, 1: string}  $labels
     */
    private static function groupByPlanNode(Collection $records, callable $key, callable $parent, string $emptyTitle, bool $emptyFirst, callable $item, array $labels): array
    {
        return $records
            ->groupBy(fn (Model $record): string => (string) ($key($record) ?? ''))
            ->map(function (Collection $items) use ($parent, $emptyTitle, $emptyFirst, $item, $labels): array {
                $node = $parent($items->first());

                return [
                    'posicion' => $node === null ? ($emptyFirst ? 0 : 2) : 1,
                    'orden' => (string) ($node?->getAttribute('codigo') ?? ''),
                    'titulo' => $node === null ? $emptyTitle : PlanLabel::full($node),
                    'nota' => self::countText($items->count(), $labels[0], $labels[1]),
                    'items' => $items->map($item)->values()->all(),
                ];
            })
            ->sortBy([['posicion', 'asc'], ['orden', 'asc']])
            ->map(fn (array $grupo): array => ['titulo' => $grupo['titulo'], 'nota' => $grupo['nota'], 'items' => $grupo['items']])
            ->values()
            ->all();
    }

    private static function countAttribute(string $relation): string
    {
        return Str::snake($relation).'_count';
    }
}
