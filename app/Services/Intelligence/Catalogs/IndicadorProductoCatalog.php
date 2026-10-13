<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\IndicadorProducto;
use App\Models\MetaProducto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndicadorProductoCatalog extends CatalogDefinition
{
    /**
     * @return array<string, string>
     */
    private function tiposAcumulacion(): array
    {
        return [
            'suma' => 'Suma',
            'constante' => 'Constante',
            'creciente' => 'Creciente',
            'decreciente' => 'Decreciente',
            'no_acumulable' => 'No acumulable',
        ];
    }

    public function modelClass(): string
    {
        return IndicadorProducto::class;
    }

    public function title(): string
    {
        return 'Indicadores producto';
    }

    public function summary(): string
    {
        return 'Indicadores operativos asociados a cada meta producto. La plantilla importa por el código de la meta producto y actualiza el indicador vinculado.';
    }

    public function routeName(): string
    {
        return 'intelligence.indicadores-producto';
    }

    public function routeParameter(): string
    {
        return 'indicadorProducto';
    }

    public function filename(): string
    {
        return 'indicadores-producto';
    }

    public function filters(): array
    {
        return ['activo', 'meta_producto_id', 'tipo_acumulacion'];
    }

    public function with(): array
    {
        return ['metaProducto.subprograma.programa.linea.eje.pilar'];
    }

    public function searchColumns(): array
    {
        return ['codigo', 'nombre', 'unidad_medida', 'metaProducto.codigo', 'metaProducto.nombre'];
    }

    public function naturalKey(): array
    {
        return ['meta_producto_id'];
    }

    public function order(Builder $query): void
    {
        $query->join('metas_producto', 'indicadores_producto.meta_producto_id', '=', 'metas_producto.id')
            ->select('indicadores_producto.*')
            ->orderBy('metas_producto.codigo');
    }

    public function fields(): array
    {
        return [
            new CatalogField(
                column: 'meta_producto_id',
                label: 'Meta producto',
                input: 'lookup',
                required: true,
                naturalKey: true,
                excelHeader: 'meta_producto_codigo',
                example: '11011014501',
                lookupModel: MetaProducto::class,
                lookupRelation: 'metaProducto',
                maxLength: 20,
                hint: 'En Excel, use el código de 11 dígitos de la meta producto. Solo puede existir un indicador producto por meta.',
            ),
            new CatalogField(
                column: 'codigo',
                label: 'Código indicador',
                input: 'text',
                unique: true,
                maxLength: 50,
                example: '',
                hint: 'Opcional. Déjelo vacío si el indicador producto no trae código oficial.',
            ),
            new CatalogField(
                column: 'nombre',
                label: 'Indicador producto',
                input: 'textarea',
                required: true,
                maxLength: 2000,
                example: 'Número de beneficiarios atendidos',
            ),
            new CatalogField(
                column: 'unidad_medida',
                label: 'Unidad',
                input: 'text',
                example: 'Número',
            ),
            new CatalogField(
                column: 'tipo_acumulacion',
                label: 'Tipo de acumulación',
                input: 'select',
                listed: true,
                options: $this->tiposAcumulacion(),
                example: 'suma',
            ),
            new CatalogField(
                column: 'linea_base',
                label: 'Línea base',
                input: 'decimal',
                listed: false,
                example: '',
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

    protected function afterValidation(Validator $validator, array $payload): void
    {
        $metaProductoId = $payload['meta_producto_id'] ?? null;

        if ($metaProductoId === null || $metaProductoId === '') {
            return;
        }

        $recordId = request()->route($this->routeParameter());

        $exists = IndicadorProducto::query()
            ->where('meta_producto_id', $metaProductoId)
            ->when($recordId, fn ($query) => $query->whereKeyNot($recordId))
            ->exists();

        if ($exists) {
            $validator->errors()->add('meta_producto_id', 'Esta meta producto ya tiene un indicador producto asociado.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<mixed>
     */
    protected function fieldRules(CatalogField $field, array $payload, bool $excel, ?int $ignoreId): array
    {
        if ($field->column !== 'codigo') {
            return parent::fieldRules($field, $payload, $excel, $ignoreId);
        }

        return [
            'nullable',
            'string',
            'max:50',
            ...($excel ? [] : [Rule::unique('indicadores_producto', 'codigo')->ignore($ignoreId)]),
        ];
    }
}
