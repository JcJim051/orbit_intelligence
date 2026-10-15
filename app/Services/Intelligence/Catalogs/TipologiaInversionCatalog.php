<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\TipologiaInversion;
use Illuminate\Database\Eloquent\Builder;

class TipologiaInversionCatalog extends CatalogDefinition
{
    public function modelClass(): string { return TipologiaInversion::class; }
    public function title(): string { return 'Tipologías de inversión'; }
    public function summary(): string { return 'Catálogo controlado para clasificar las metas producto. La tipología siempre se muestra como código — nombre.'; }
    public function routeName(): string { return 'intelligence.tipologias-inversion'; }
    public function routeParameter(): string { return 'tipologiaInversion'; }
    public function filename(): string { return 'tipologias-inversion'; }

    public function filters(): array { return ['activo']; }
    public function counts(): array { return ['metasProducto' => 'Metas producto']; }
    public function countDetails(): array { return ['metasProducto']; }
    public function searchColumns(): array { return ['codigo', 'nombre', 'descripcion']; }
    public function naturalKey(): array { return ['codigo']; }

    public function order(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('codigo');
    }

    public function newQuery(): Builder
    {
        return TipologiaInversion::query()
            ->withCount('metasProducto')
            ->orderBy('orden')
            ->orderBy('codigo');
    }

    public function countDetail(\Illuminate\Database\Eloquent\Model $record, string $relation): ?array
    {
        return $relation === 'metasProducto'
            ? CountDetail::metasProducto($record, 'Metas producto clasificadas', $record->metasProducto(), ['metaResultado', 'sectorMga', 'dependencia'])
            : null;
    }

    public function fields(): array
    {
        return [
            new CatalogField(
                column: 'codigo', label: 'Código', input: 'text', required: true, naturalKey: true,
                maxLength: 10, example: 'TI1', extraRules: ['regex:/^TI[1-5]$/'], unique: true,
            ),
            new CatalogField(
                column: 'nombre', label: 'Nombre', input: 'text', required: true,
                maxLength: 180, example: 'Impacto territorial (infraestructura y obra)',
            ),
            new CatalogField(
                column: 'descripcion', label: 'Descripción', input: 'textarea',
                maxLength: 2000, example: 'Clasificación de inversión de ejemplo.',
            ),
            new CatalogField(
                column: 'orden', label: 'Orden', input: 'number', required: true,
                minNumber: 1, maxNumber: 999, example: 1,
            ),
            new CatalogField(column: 'activo', label: 'Activo', input: 'boolean', required: true, example: 1),
        ];
    }

    protected function afterValidation(\Illuminate\Validation\Validator $validator, array $payload): void
    {
        $codigo = $payload['codigo'] ?? null;
        $recordId = request()->route($this->routeParameter());

        if ($codigo !== null && TipologiaInversion::query()->where('codigo', $codigo)->when($recordId, fn ($q) => $q->whereKeyNot($recordId))->exists()) {
            $validator->errors()->add('codigo', 'Ya existe una tipología con ese código.');
        }
    }
}
