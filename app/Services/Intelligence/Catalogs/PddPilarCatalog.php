<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\PddPilar;

class PddPilarCatalog extends PlanNodeCatalog
{
    public function modelClass(): string
    {
        return PddPilar::class;
    }

    public function title(): string
    {
        return 'Pilares';
    }

    public function summary(): string
    {
        return 'Primer nivel del Plan de Desarrollo. El código de 11 dígitos es el de producción; el numeral es el rótulo (1, 2, 3).';
    }

    public function routeName(): string
    {
        return 'intelligence.pilares';
    }

    public function routeParameter(): string
    {
        return 'pilar';
    }

    public function filename(): string
    {
        return 'pilares';
    }

    public function counts(): array
    {
        return ['ejes' => 'Ejes'];
    }

    public function childRelations(): array
    {
        return ['ejes' => 'ejes'];
    }

    protected function parentField(): ?CatalogField
    {
        return null;
    }
}
