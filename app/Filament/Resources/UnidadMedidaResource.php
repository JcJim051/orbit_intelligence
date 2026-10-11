<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnidadMedidaResource\Pages\ListUnidadMedidaResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\UnidadMedida;

class UnidadMedidaResource extends CatalogoSuelosResource
{
    protected static ?string $model = UnidadMedida::class;

    protected static ?string $navigationLabel = 'Unidades de medida';

    protected static ?string $modelLabel = 'Unidades de medida';

    protected static ?string $pluralModelLabel = 'Unidades de medida';

    protected static ?string $slug = 'unidades-medida';

    protected static ?int $navigationSort = 140;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'simbolo', 'label' => 'Símbolo', 'tipo' => 'texto', 'requerido' => true],
        ['name' => 'magnitud', 'label' => 'Magnitud', 'tipo' => 'texto', 'requerido' => true],
        ['name' => 'factor_a_base', 'label' => 'Factor a la base', 'tipo' => 'numero'],
        ['name' => 'unidad_base_id', 'label' => 'Unidad base', 'tipo' => 'relacion', 'excel' => 'unidad_base', 'modelo' => UnidadMedida::class],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'unidades';
    }

    public static function paginaListado(): string
    {
        return ListUnidadMedidaResource::class;
    }
}
