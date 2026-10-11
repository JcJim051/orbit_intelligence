<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClasificacionAashtoResource\Pages\ListClasificacionAashtoResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\ClasificacionAashto;

class ClasificacionAashtoResource extends CatalogoSuelosResource
{
    protected static ?string $model = ClasificacionAashto::class;

    protected static ?string $navigationLabel = 'Clasificación AASHTO';

    protected static ?string $modelLabel = 'Clasificación AASHTO';

    protected static ?string $pluralModelLabel = 'Clasificaciones AASHTO';

    protected static ?string $slug = 'clasificacion-aashto';

    protected static ?int $navigationSort = 120;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'descripcion', 'label' => 'Descripción', 'tipo' => 'texto_largo'],
        ['name' => 'orden', 'label' => 'Orden', 'tipo' => 'numero'],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'aashto';
    }

    public static function paginaListado(): string
    {
        return ListClasificacionAashtoResource::class;
    }
}
