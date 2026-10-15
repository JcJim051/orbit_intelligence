<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\Dependencia;
use App\Models\MetaProducto;
use App\Models\MetaResultado;
use App\Models\PddSubprograma;
use App\Models\SectorMga;
use App\Models\TipologiaInversion;

class MetaProductoCatalog extends CatalogDefinition
{
    public function modelClass(): string
    {
        return MetaProducto::class;
    }

    public function title(): string
    {
        return 'Metas producto';
    }

    public function summary(): string
    {
        return 'Metas de producto del plan. El código es el de producción (codigo_meta_plan). La dependencia se asigna después, cuando Planeación la confirme.';
    }

    public function routeName(): string
    {
        return 'intelligence.metas-producto';
    }

    public function routeParameter(): string
    {
        return 'metaProducto';
    }

    public function filename(): string
    {
        return 'metas-producto';
    }

    public function filters(): array
    {
        return ['activo', 'meta_resultado_id', 'subprograma_id', 'sector_mga_id', 'dependencia_id', 'tipologia_inversion_id'];
    }

    public function with(): array
    {
        return ['subprograma', 'sectorMga', 'metaResultado', 'dependencia', 'tipologiaInversion'];
    }

    public function searchColumns(): array
    {
        return ['codigo', 'nombre', 'tipologiaInversion.codigo', 'tipologiaInversion.nombre'];
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
                maxLength: 20,
                example: '11011014501',
                extraRules: ['regex:/^\d{11}$/'],
                hint: 'Código de la meta de producto, 11 dígitos.',
            ),
            new CatalogField(
                column: 'nombre',
                label: 'Nombre',
                input: 'textarea',
                required: true,
                maxLength: 2000,
                example: 'Meta de producto de ejemplo',
            ),
            new CatalogField(
                column: 'indicador_producto',
                label: 'Indicador producto',
                input: 'textarea',
                required: false,
                maxLength: 2000,
                example: 'Indicador de producto de ejemplo',
                hint: 'Indicador operativo con el que se mide esta meta producto.',
            ),
            new CatalogField(
                column: 'subprograma_id',
                label: 'Subprograma',
                input: 'lookup',
                required: true,
                excelHeader: 'subprograma_codigo',
                example: '11011010000',
                lookupModel: PddSubprograma::class,
                lookupRelation: 'subprograma',
                maxLength: 20,
                hint: 'En Excel, el código de 11 dígitos del subprograma.',
            ),
            new CatalogField(
                column: 'sector_mga_id',
                label: 'Sector MGA',
                input: 'lookup',
                required: true,
                excelHeader: 'sector_codigo',
                example: '45',
                lookupModel: SectorMga::class,
                lookupColumn: 'codigo',
                lookupRelation: 'sectorMga',
                maxLength: 8,
                hint: 'En Excel, el código de dos dígitos del sector.',
            ),
            new CatalogField(
                column: 'meta_resultado_id',
                label: 'Meta resultado',
                input: 'lookup',
                excelHeader: 'meta_resultado_codigo',
                example: 'MR-001',
                lookupModel: MetaResultado::class,
                lookupColumn: 'codigo_provisional',
                lookupDisplay: 'descripcion',
                lookupRelation: 'metaResultado',
                maxLength: 20,
                hint: 'Opcional. En Excel, el código provisional (MR-001), no un código oficial.',
            ),
            new CatalogField(
                column: 'dependencia_id',
                label: 'Dependencia',
                input: 'dependencia',
                required: false,
                onForm: true,
                excelHeader: 'dependencia_codigo',
                example: '',
                lookupModel: Dependencia::class,
                hint: 'Opcional. En Excel, el código de la dependencia. Déjelo vacío si todavía no está confirmada.',
            ),
            new CatalogField(
                column: 'tipologia_inversion_id',
                label: 'Tipología de inversión',
                input: 'lookup',
                excelHeader: 'tipologia_inversion_codigo',
                example: 'TI1',
                lookupModel: TipologiaInversion::class,
                lookupColumn: 'codigo',
                lookupRelation: 'tipologiaInversion',
                hint: 'Opcional. En Excel use TI1, TI2, TI3, TI4 o TI5.',
            ),
            new CatalogField(
                column: 'tipologia_observacion',
                label: 'Observación de tipología',
                input: 'textarea',
                listed: false,
                onExcel: false,
                maxLength: 2000,
            ),
            new CatalogField(
                column: 'tipologia_clasificada_por',
                label: 'Clasificada por',
                input: 'text',
                listed: false,
                onForm: false,
                onExcel: false,
            ),
            new CatalogField(
                column: 'tipologia_clasificada_at',
                label: 'Fecha de clasificación',
                input: 'text',
                listed: false,
                onForm: false,
                onExcel: false,
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
