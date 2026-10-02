<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\PddEje;
use App\Models\PddPilar;
use Illuminate\Database\Eloquent\Model;

class PddEjeCatalog extends PlanNodeCatalog
{
    public function modelClass(): string
    {
        return PddEje::class;
    }

    public function title(): string
    {
        return 'Ejes';
    }

    public function summary(): string
    {
        return 'Ejes estratégicos del Plan de Desarrollo, cada uno dentro de un pilar.';
    }

    public function routeName(): string
    {
        return 'intelligence.ejes';
    }

    public function routeParameter(): string
    {
        return 'eje';
    }

    public function filename(): string
    {
        return 'ejes';
    }

    public function with(): array
    {
        return ['pilar'];
    }

    public function counts(): array
    {
        return ['lineas' => 'Líneas'];
    }

    public function countDetails(): array
    {
        return ['lineas'];
    }

    public function countDetail(Model $record, string $relation): ?array
    {
        return match ($relation) {
            'lineas' => CountDetail::planTree($record, 'Líneas', $record->lineas(), ['línea', 'líneas'], 'programas', ['programa', 'programas'], [
                'subprogramas' => ['subprograma', 'subprogramas'],
                'metasResultado' => ['meta resultado', 'metas resultado'],
            ]),
            default => null,
        };
    }

    public function childRelations(): array
    {
        return ['lineas' => 'líneas'];
    }

    protected function parentField(): ?CatalogField
    {
        return new CatalogField(
            column: 'pilar_id',
            label: 'Pilar',
            input: 'lookup',
            required: true,
            excelHeader: 'pilar_codigo',
            example: '10000000000',
            lookupModel: PddPilar::class,
            lookupRelation: 'pilar',
            maxLength: 20,
            hint: 'En Excel, el código de 11 dígitos del pilar.',
        );
    }
}
