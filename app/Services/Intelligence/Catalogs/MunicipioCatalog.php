<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\Municipio;
use Illuminate\Database\Eloquent\Builder;

class MunicipioCatalog extends CatalogDefinition
{
    public function modelClass(): string
    {
        return Municipio::class;
    }

    public function title(): string
    {
        return 'Municipios del Meta';
    }

    public function summary(): string
    {
        return 'Los 29 municipios del Meta con código DANE de 5 dígitos. El mapa de inversión pública no tenía esta tabla: guardaba el municipio como texto.';
    }

    public function routeName(): string
    {
        return 'intelligence.municipios';
    }

    public function routeParameter(): string
    {
        return 'municipio';
    }

    public function filename(): string
    {
        return 'municipios';
    }

    public function searchColumns(): array
    {
        return ['codigo_dane', 'nombre', 'subregion'];
    }

    public function naturalKey(): array
    {
        return ['codigo_dane'];
    }

    public function order(Builder $query): void
    {
        $query->orderBy('nombre');
    }

    public function fields(): array
    {
        return [
            new CatalogField(
                column: 'codigo_dane',
                label: 'Código DANE',
                input: 'text',
                required: true,
                naturalKey: true,
                maxLength: 5,
                example: '50999',
                hint: 'Cinco dígitos. No reutilice un código oficial si solo está armando un ejemplo.',
                extraRules: ['regex:/^\d{5}$/'],
            ),
            new CatalogField(
                column: 'nombre',
                label: 'Nombre',
                input: 'text',
                required: true,
                example: 'Municipio de ejemplo',
            ),
            new CatalogField(
                column: 'subregion',
                label: 'Subregión',
                input: 'text',
                maxLength: 80,
                example: 'Subregión de ejemplo',
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
