<?php

namespace App\Services\Intelligence\Catalogs;

use App\Enums\TipoDependencia;
use App\Models\Dependencia;
use Illuminate\Database\Eloquent\Builder;

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
                example: 'Secretaría de ejemplo',
            ),
            new CatalogField(
                column: 'sigla',
                label: 'Sigla',
                input: 'text',
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
