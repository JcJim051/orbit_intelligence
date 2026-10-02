<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\PddEje;
use App\Models\PddLinea;
use Illuminate\Database\Eloquent\Model;

class PddLineaCatalog extends PlanNodeCatalog
{
    public function modelClass(): string
    {
        return PddLinea::class;
    }

    public function title(): string
    {
        return 'Líneas';
    }

    public function summary(): string
    {
        return 'Líneas estratégicas del Plan de Desarrollo, cada una dentro de un eje.';
    }

    public function routeName(): string
    {
        return 'intelligence.lineas';
    }

    public function routeParameter(): string
    {
        return 'linea';
    }

    public function filename(): string
    {
        return 'lineas';
    }

    public function with(): array
    {
        return ['eje'];
    }

    public function counts(): array
    {
        return ['programas' => 'Programas'];
    }

    public function countDetails(): array
    {
        return ['programas'];
    }

    public function countDetail(Model $record, string $relation): ?array
    {
        return match ($relation) {
            'programas' => CountDetail::planTree($record, 'Programas', $record->programas(), ['programa', 'programas'], 'subprogramas', ['subprograma', 'subprogramas'], [
                'metasProducto' => ['meta producto', 'metas producto'],
                'metasResultado' => ['meta resultado', 'metas resultado'],
            ]),
            default => null,
        };
    }

    public function childRelations(): array
    {
        return ['programas' => 'programas'];
    }

    protected function parentField(): ?CatalogField
    {
        return new CatalogField(
            column: 'eje_id',
            label: 'Eje',
            input: 'lookup',
            required: true,
            excelHeader: 'eje_codigo',
            example: '11000000000',
            lookupModel: PddEje::class,
            lookupRelation: 'eje',
            maxLength: 20,
            hint: 'En Excel, el código de 11 dígitos del eje.',
        );
    }
}
