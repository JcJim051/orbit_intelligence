<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\Trazador;
use App\Models\TrazadorCategoria;

class TrazadorCategoriaCatalog extends CatalogDefinition
{
    public function modelClass(): string
    {
        return TrazadorCategoria::class;
    }

    public function title(): string
    {
        return 'Categorías de trazadores';
    }

    public function summary(): string
    {
        return 'Categorías en las que se distribuyen los valores de cada trazador presupuestal.';
    }

    public function routeName(): string
    {
        return 'intelligence.trazador-categorias';
    }

    public function routeParameter(): string
    {
        return 'trazadorCategoria';
    }

    public function filename(): string
    {
        return 'categorias-trazadores';
    }

    public function searchColumns(): array
    {
        return ['nombre'];
    }

    public function naturalKey(): array
    {
        return ['trazador_id', 'nombre'];
    }

    public function with(): array
    {
        return ['trazador'];
    }

    public function filters(): array
    {
        return ['trazador_id', 'activo'];
    }

    public function childRelations(): array
    {
        return ['asociaciones' => 'asociaciones a proyectos'];
    }

    public function fields(): array
    {
        return [
            new CatalogField('trazador_id', 'Trazador', 'lookup', true, true, lookupModel: Trazador::class, lookupColumn: 'codigo', lookupRelation: 'trazador', lookupDisplay: 'nombre'),
            new CatalogField('nombre', 'Categoría', 'text', true, true, maxLength: 500, example: 'Autonomía económica'),
            new CatalogField('activo', 'Activo', 'boolean', true, example: 1),
        ];
    }
}
