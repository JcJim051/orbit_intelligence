<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\Trazador;
use Illuminate\Database\Eloquent\Builder;

class TrazadorCatalog extends CatalogDefinition
{
    public function modelClass(): string
    {
        return Trazador::class;
    }

    public function title(): string
    {
        return 'Trazadores presupuestales';
    }

    public function summary(): string
    {
        return 'Catálogo departamental de marcaciones presupuestales. Las categorías se administran por separado.';
    }

    public function routeName(): string
    {
        return 'intelligence.trazadores';
    }

    public function routeParameter(): string
    {
        return 'trazador';
    }

    public function filename(): string
    {
        return 'trazadores-presupuestales';
    }

    public function searchColumns(): array
    {
        return ['codigo', 'nombre', 'descripcion'];
    }

    public function naturalKey(): array
    {
        return ['nombre'];
    }

    public function counts(): array
    {
        return ['categorias' => 'Categorías', 'proyectos' => 'Proyectos'];
    }

    public function childRelations(): array
    {
        return ['categorias' => 'categorías', 'proyectos' => 'proyectos'];
    }

    public function order(Builder $query): void
    {
        $query->orderBy('codigo');
    }

    public function fields(): array
    {
        return [
            new CatalogField('codigo', 'Código', 'text', listed: true, onForm: false, onExcel: false, maxLength: 20),
            new CatalogField('nombre', 'Nombre', 'text', true, true, maxLength: 255, example: 'Equidad de la Mujer', unique: true),
            new CatalogField('descripcion', 'Descripción', 'textarea', maxLength: 3000),
            new CatalogField('activo', 'Activo', 'boolean', true, example: 1),
        ];
    }
}
