<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\PoliticaPublicaDepartamental;

class PoliticaPublicaDepartamentalCatalog extends CatalogDefinition
{
    public function modelClass(): string
    {
        return PoliticaPublicaDepartamental::class;
    }

    public function title(): string
    {
        return 'Políticas públicas departamentales';
    }

    public function summary(): string
    {
        return 'Catálogo opcional para clasificar cada relación entre proyecto y trazador.';
    }

    public function routeName(): string
    {
        return 'intelligence.politicas-publicas-departamentales';
    }

    public function routeParameter(): string
    {
        return 'politicaPublicaDepartamental';
    }

    public function filename(): string
    {
        return 'politicas-publicas-departamentales';
    }

    public function searchColumns(): array
    {
        return ['nombre'];
    }

    public function naturalKey(): array
    {
        return ['nombre'];
    }

    public function childRelations(): array
    {
        return ['proyectosTrazadores' => 'asociaciones a proyectos'];
    }

    public function fields(): array
    {
        return [
            new CatalogField('nombre', 'Nombre', 'text', true, true, maxLength: 500, example: 'Política Pública Departamental de Equidad de Género para las Mujeres', unique: true),
            new CatalogField('activo', 'Activo', 'boolean', true, example: 1),
        ];
    }
}
