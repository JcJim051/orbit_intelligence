<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EstadoValidacionResource\Pages\ListEstadoValidacionResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\EstadoValidacion;

class EstadoValidacionResource extends CatalogoSuelosResource
{
    protected static ?string $model = EstadoValidacion::class;

    protected static ?string $navigationLabel = 'Estados de validación';

    protected static ?string $modelLabel = 'Estados de validación';

    protected static ?string $pluralModelLabel = 'Estados de validación';

    protected static ?string $slug = 'estados-validacion';

    protected static ?int $navigationSort = 220;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'es_final', 'label' => 'Es final', 'tipo' => 'bool'],
        ['name' => 'visible_en_informe', 'label' => 'Visible en el informe', 'tipo' => 'bool'],
        ['name' => 'orden', 'label' => 'Orden', 'tipo' => 'numero'],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'estados-validacion';
    }

    public static function paginaListado(): string
    {
        return ListEstadoValidacionResource::class;
    }
}
