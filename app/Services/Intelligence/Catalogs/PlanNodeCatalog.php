<?php

namespace App\Services\Intelligence\Catalogs;

abstract class PlanNodeCatalog extends CatalogDefinition
{
    abstract protected function parentField(): ?CatalogField;

    public function fields(): array
    {
        $fields = [
            new CatalogField(
                column: 'codigo',
                label: 'Código',
                input: 'text',
                required: true,
                naturalKey: true,
                maxLength: 20,
                example: '10000000000',
                extraRules: ['regex:/^\d{11}$/'],
                hint: 'Código de producción de 11 dígitos.',
            ),
            new CatalogField(
                column: 'numeral',
                label: 'Numeral',
                input: 'text',
                maxLength: 32,
                example: '1.1',
                hint: 'Rótulo punteado tomado del nombre, por ejemplo 1.1.1. Puede quedar vacío.',
            ),
            new CatalogField(
                column: 'nombre',
                label: 'Nombre',
                input: 'textarea',
                required: true,
                maxLength: 2000,
                example: 'Nombre de ejemplo',
            ),
        ];

        $parent = $this->parentField();

        if ($parent instanceof CatalogField) {
            $fields[] = $parent;
        }

        $fields[] = new CatalogField(
            column: 'activo',
            label: 'Activo',
            input: 'boolean',
            required: true,
            example: 1,
        );

        return $fields;
    }

    public function searchColumns(): array
    {
        return ['codigo', 'numeral', 'nombre'];
    }

    public function naturalKey(): array
    {
        return ['codigo'];
    }
}
