<?php

namespace App\Services\Intelligence\Catalogs;

use App\Enums\TipoDependencia;
use App\Models\Dependencia;
use App\Models\MetaProducto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DependenciaCatalog extends CatalogDefinition
{
    public function modelClass(): string
    {
        return Dependencia::class;
    }

    public function title(): string
    {
        return 'Dependencias';
    }

    public function summary(): string
    {
        return 'Secretarías y entidades descentralizadas que entregan la matriz mensual. El tipo es una clasificación editable.';
    }

    public function routeName(): string
    {
        return 'intelligence.dependencias';
    }

    public function routeParameter(): string
    {
        return 'dependencia';
    }

    public function filename(): string
    {
        return 'dependencias';
    }

    public function filters(): array
    {
        return ['activo', 'tipo'];
    }

    public function searchColumns(): array
    {
        return ['codigo', 'nombre', 'sigla', 'hoja_matriz'];
    }

    public function naturalKey(): array
    {
        return ['codigo'];
    }

    public function order(Builder $query): void
    {
        $query->orderBy('codigo');
    }

    public function newQuery(): Builder
    {
        $query = Dependencia::query()
            ->select('dependencias.*')
            ->withCount('metasProducto')
            ->selectSub(
                MetaProducto::query()
                    ->selectRaw('count(distinct meta_resultado_id)')
                    ->whereColumn('dependencia_id', 'dependencias.id')
                    ->whereNotNull('meta_resultado_id')
                    ->whereNull('deleted_at'),
                'metas_resultado_count',
            );

        return $query;
    }

    public function counts(): array
    {
        return [
            'metasResultado' => 'Metas resultado',
            'metasProducto' => 'Metas producto',
        ];
    }

    public function countDetails(): array
    {
        return ['metasResultado', 'metasProducto'];
    }

    public function countDetail(Model $record, string $relation): ?array
    {
        return match ($relation) {
            'metasResultado' => CountDetail::metasResultado($record, 'Metas resultado', $record->metasResultado(), 'subprograma', 'Sin subprograma'),
            'metasProducto' => CountDetail::metasProducto($record, 'Metas producto', $record->metasProducto(), ['metaResultado', 'sectorMga']),
            default => null,
        };
    }

    public function childRelations(): array
    {
        return ['metasProducto' => 'metas de producto'];
    }

    public function fields(): array
    {
        return [
            new CatalogField(
                column: 'codigo',
                label: 'Código',
                input: 'text',
                required: true,
                naturalKey: true,
                maxLength: 50,
                example: 'SEC. EJEMPLO',
                hint: 'Código corto y único, como SEC. SALUD o AIM.',
            ),
            new CatalogField(
                column: 'nombre',
                label: 'Nombre oficial',
                input: 'text',
                required: true,
                listed: false,
                example: 'Secretaría de ejemplo',
            ),
            new CatalogField(
                column: 'sigla',
                label: 'Sigla',
                input: 'text',
                listed: false,
                maxLength: 120,
                example: 'Ejemplo',
            ),
            new CatalogField(
                column: 'tipo',
                label: 'Tipo',
                input: 'select',
                required: true,
                naturalKey: false,
                options: TipoDependencia::options(),
                example: 'central',
            ),
            new CatalogField(
                column: 'hoja_matriz',
                label: 'Hoja de la matriz',
                input: 'text',
                listed: false,
                maxLength: 120,
                example: 'SEC. EJEMPLO',
                hint: 'Nombre de la hoja de esta dependencia en el libro mensual.',
            ),
            new CatalogField(
                column: 'activo',
                label: 'Activo',
                input: 'boolean',
                required: true,
                example: 1,
            ),
        ];
    }
}
