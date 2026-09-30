<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\SectorMga;

class SectorMgaCatalog extends CatalogDefinition
{
    public function modelClass(): string
    {
        return SectorMga::class;
    }

    public function title(): string
    {
        return 'Sectores MGA';
    }

    public function summary(): string
    {
        return 'Sectores de la Metodología General Ajustada. El código se guarda con dos dígitos, por ejemplo 04 o 45.';
    }

    public function routeName(): string
    {
        return 'intelligence.sectores-mga';
    }

    public function routeParameter(): string
    {
        return 'sector';
    }

    public function filename(): string
    {
        return 'sectores-mga';
    }

    public function counts(): array
    {
        return ['metasProducto' => 'Metas producto'];
    }

    public function childRelations(): array
    {
        return ['metasProducto' => 'metas de producto'];
    }

    public function searchColumns(): array
    {
        return ['codigo', 'nombre'];
    }

    public function naturalKey(): array
    {
        return ['codigo'];
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
                maxLength: 8,
                example: '45',
                extraRules: ['regex:/^\d{2}$/'],
                hint: 'Dos dígitos. Conserve el cero a la izquierda, como en 04.',
            ),
            new CatalogField(
                column: 'nombre',
                label: 'Nombre',
                input: 'text',
                required: true,
                example: 'Gobierno Territorial',
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

    protected function messages(): array
    {
        return [
            ...parent::messages(),
            'codigo.regex' => 'El código del sector debe tener 2 dígitos.',
        ];
    }
}
