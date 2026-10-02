<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\IndicadorResultado;
use App\Models\MetaResultado;
use App\Models\PddPrograma;
use App\Models\PddSubprograma;
use Illuminate\Validation\Validator;
use Illuminate\Database\Eloquent\Model;

class MetaResultadoCatalog extends CatalogDefinition
{
    public function modelClass(): string
    {
        return MetaResultado::class;
    }

    public function title(): string
    {
        return 'Metas resultado';
    }

    public function summary(): string
    {
        return 'Metas de resultado de la matriz mensual. No tienen código oficial: se identifican con un código provisional (MR-001). Cada una apunta a un solo indicador.';
    }

    public function routeName(): string
    {
        return 'intelligence.metas-resultado';
    }

    public function routeParameter(): string
    {
        return 'metaResultado';
    }

    public function filename(): string
    {
        return 'metas-resultado';
    }

    public function filters(): array
    {
        return ['activo', 'programa_id', 'indicador_resultado_id'];
    }

    public function with(): array
    {
        return ['programa', 'subprograma', 'indicador', 'metasProducto:id,codigo,nombre,meta_resultado_id'];
    }

    public function counts(): array
    {
        return ['metasProducto' => 'Metas producto'];
    }

    public function countDetails(): array
    {
        return ['metasProducto'];
    }

    public function countDetail(Model $record, string $relation): ?array
    {
        return match ($relation) {
            'metasProducto' => CountDetail::metasProducto($record, 'Metas producto', $record->metasProducto(), ['sectorMga', 'dependencia']),
            default => null,
        };
    }

    public function countPreview(Model $record, string $relation, int $count): ?array
    {
        if ($relation !== 'metasProducto' || $count < 1) {
            return null;
        }

        $metas = $record->relationLoaded('metasProducto')
            ? $record->getRelation('metasProducto')->sortBy('codigo')->values()
            : $record->metasProducto()->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'meta_resultado_id']);
        $lines = $metas->map(fn ($meta): string => $meta->codigo.' — '.$meta->nombre);

        if ($count === 1 && $lines->count() === 1) {
            $full = (string) $lines->first();

            return [
                'label' => mb_strlen($full) > 70 ? mb_substr($full, 0, 69).'…' : $full,
                'title' => $full,
            ];
        }

        $title = $lines->take(10)->implode("\n").($lines->count() > 10 ? "\n…" : '');

        return ['label' => $count.' metas producto', 'title' => $title];
    }

    public function childRelations(): array
    {
        return ['metasProducto' => 'metas de producto'];
    }

    public function searchColumns(): array
    {
        return ['codigo', 'codigo_provisional', 'descripcion', 'observacion'];
    }

    public function naturalKey(): array
    {
        return ['codigo_provisional'];
    }

    public function fields(): array
    {
        return [
            new CatalogField(
                column: 'codigo',
                label: 'Código oficial',
                input: 'text',
                unique: true,
                maxLength: 20,
                example: '',
                hint: 'Opcional. La matriz no trae código; no lo invente.',
            ),
            new CatalogField(
                column: 'codigo_provisional',
                label: 'Código provisional',
                input: 'text',
                required: true,
                naturalKey: true,
                maxLength: 20,
                example: 'MR-001',
                hint: 'Llave de la importación mientras no exista código oficial.',
            ),
            new CatalogField(
                column: 'descripcion',
                label: 'Descripción',
                input: 'textarea',
                required: true,
                maxLength: 2000,
                example: 'Meta de resultado de ejemplo',
            ),
            new CatalogField(
                column: 'programa_id',
                label: 'Programa',
                input: 'lookup',
                excelHeader: 'programa_codigo',
                example: '11011000000',
                lookupModel: PddPrograma::class,
                lookupRelation: 'programa',
                maxLength: 20,
                hint: 'Opcional. En Excel, el código de 11 dígitos del programa.',
            ),
            new CatalogField(
                column: 'subprograma_id',
                label: 'Subprograma',
                input: 'lookup',
                excelHeader: 'subprograma_codigo',
                example: '',
                lookupModel: PddSubprograma::class,
                lookupRelation: 'subprograma',
                dependsOn: 'programa_id',
                parentAttribute: 'programa_id',
                maxLength: 20,
                hint: 'Opcional. Si se informa, debe pertenecer al programa. En Excel, código de 11 dígitos.',
            ),
            new CatalogField(
                column: 'indicador_resultado_id',
                label: 'Indicador',
                input: 'lookup',
                required: true,
                excelHeader: 'indicador_nombre',
                example: 'Tasa de ejemplo',
                lookupModel: IndicadorResultado::class,
                lookupColumn: 'nombre',
                lookupDisplay: 'nombre',
                lookupRelation: 'indicador',
                maxLength: 255,
                hint: 'En Excel, el nombre exacto del indicador. No hay código oficial.',
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
                column: 'observacion',
                label: 'Observación',
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
        $subprograma = null;

        if (! empty($payload['subprograma_id'])) {
            $subprograma = PddSubprograma::query()->find($payload['subprograma_id']);
        } elseif (! empty($payload['subprograma_codigo'])) {
            $subprograma = PddSubprograma::query()->where('codigo', $payload['subprograma_codigo'])->first();
        }

        if (! $subprograma instanceof PddSubprograma) {
            return;
        }

        $programaId = $payload['programa_id'] ?? null;

        if (! empty($payload['programa_codigo'])) {
            $programaId = PddPrograma::query()->where('codigo', $payload['programa_codigo'])->value('id');
        }

        if ($programaId === null || $programaId === '') {
            return;
        }

        if ((int) $programaId !== (int) $subprograma->programa_id) {
            $key = array_key_exists('subprograma_codigo', $payload) ? 'subprograma_codigo' : 'subprograma_id';
            $validator->errors()->add($key, 'El subprograma no pertenece al programa indicado.');
        }
    }
}
