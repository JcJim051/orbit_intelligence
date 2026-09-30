<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\PddPrograma;
use App\Models\PddSubprograma;

class PddSubprogramaCatalog extends PlanNodeCatalog
{
    public function modelClass(): string
    {
        return PddSubprograma::class;
    }

    public function title(): string
    {
        return 'Subprogramas';
    }

    public function summary(): string
    {
        return 'Subprogramas del Plan de Desarrollo, cada uno dentro de un programa.';
    }

    public function routeName(): string
    {
        return 'intelligence.subprogramas';
    }

    public function routeParameter(): string
    {
        return 'subprograma';
    }

    public function filename(): string
    {
        return 'subprogramas';
    }

    public function with(): array
    {
        return ['programa'];
    }

    public function counts(): array
    {
        return [
            'metasProducto' => 'Metas producto',
            'metasResultado' => 'Metas resultado',
        ];
    }

    public function childRelations(): array
    {
        return [
            'metasProducto' => 'metas de producto',
            'metasResultado' => 'metas de resultado',
        ];
    }

    protected function parentField(): ?CatalogField
    {
        return new CatalogField(
            column: 'programa_id',
            label: 'Programa',
            input: 'lookup',
            required: true,
            excelHeader: 'programa_codigo',
            example: '11011000000',
            lookupModel: PddPrograma::class,
            lookupRelation: 'programa',
            maxLength: 20,
            hint: 'En Excel, el código de 11 dígitos del programa.',
        );
    }
}
