<?php

namespace App\Services\Intelligence\Catalogs;

use App\Enums\OrientacionIndicador;
use App\Models\IndicadorResultado;
use Illuminate\Database\Eloquent\Model;

class IndicadorResultadoCatalog extends CatalogDefinition
{
    public function modelClass(): string
    {
        return IndicadorResultado::class;
    }

    public function title(): string
    {
        return 'Indicadores de resultado';
    }

    public function summary(): string
    {
        return 'Un indicador por nombre. La matriz no trae código oficial: no se inventa. La importación actualiza por el nombre.';
    }

    public function routeName(): string
    {
        return 'intelligence.indicadores-resultado';
    }

    public function routeParameter(): string
    {
        return 'indicador';
    }

    public function filename(): string
    {
        return 'indicadores-resultado';
    }

    public function counts(): array
    {
        return ['metasResultado' => 'Metas resultado'];
    }

    public function countDetails(): array
    {
        return ['metasResultado'];
    }

    public function countDetail(Model $record, string $relation): ?array
    {
        return match ($relation) {
            'metasResultado' => CountDetail::metasResultado($record, 'Metas resultado', $record->metasResultado(), 'programa', 'Sin programa', showIndicador: false),
            default => null,
        };
    }

    public function childRelations(): array
    {
        return ['metasResultado' => 'metas de resultado'];
    }

    public function searchColumns(): array
    {
        return ['codigo', 'nombre', 'unidad_medida'];
    }

    public function naturalKey(): array
    {
        return ['nombre'];
    }

    public function fields(): array
    {
        return [
            new CatalogField(
                column: 'codigo',
                label: 'Código',
                input: 'text',
                unique: true,
                maxLength: 50,
                example: '',
                hint: 'Opcional. Déjelo vacío mientras Planeación no asigne un código oficial.',
            ),
            new CatalogField(
                column: 'nombre',
                label: 'Nombre',
                input: 'textarea',
                required: true,
                naturalKey: true,
                maxLength: 255,
                example: 'Tasa de ejemplo',
            ),
            new CatalogField(
                column: 'unidad_medida',
                label: 'Unidad de medida',
                input: 'text',
                example: 'Porcentaje',
            ),
            new CatalogField(
                column: 'orientacion',
                label: 'Orientación',
                input: 'select',
                options: OrientacionIndicador::options(),
                example: 'incremento',
            ),
            new CatalogField(
                column: 'linea_base',
                label: 'Línea base',
                input: 'decimal',
                listed: false,
                example: '',
            ),
            new CatalogField(
                column: 'linea_base_texto',
                label: 'Línea base (texto)',
                input: 'text',
                listed: false,
                example: '',
                hint: 'Use este campo cuando la línea base no sea un número.',
            ),
            new CatalogField(
                column: 'meta_cuatrienio',
                label: 'Meta cuatrienio',
                input: 'decimal',
                listed: false,
                example: '',
            ),
            new CatalogField(
                column: 'fuente_verificacion',
                label: 'Fuente de verificación',
                input: 'textarea',
                listed: false,
                maxLength: 2000,
                example: '',
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
