<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\PddLinea;
use App\Models\PddPrograma;

class PddProgramaCatalog extends PlanNodeCatalog
{
    public function modelClass(): string
    {
        return PddPrograma::class;
    }

    public function title(): string
    {
        return 'Programas';
    }

    public function summary(): string
    {
        return 'Programas del Plan de Desarrollo. Algunos registros de producción no traen numeral en el nombre; en ese caso el numeral queda vacío.';
    }

    public function routeName(): string
    {
        return 'intelligence.programas';
    }

    public function routeParameter(): string
    {
        return 'programa';
    }

    public function filename(): string
    {
        return 'programas';
    }

    public function with(): array
    {
        return ['linea'];
    }

    public function counts(): array
    {
        return [
            'subprogramas' => 'Subprogramas',
            'metasResultado' => 'Metas resultado',
        ];
    }

    public function childRelations(): array
    {
        return [
            'subprogramas' => 'subprogramas',
            'metasResultado' => 'metas de resultado',
        ];
    }

    protected function parentField(): ?CatalogField
    {
        return new CatalogField(
            column: 'linea_id',
            label: 'Línea',
            input: 'lookup',
            required: true,
            excelHeader: 'linea_codigo',
            example: '11010000000',
            lookupModel: PddLinea::class,
            lookupRelation: 'linea',
            maxLength: 20,
            hint: 'En Excel, el código de 11 dígitos de la línea.',
        );
    }
}
