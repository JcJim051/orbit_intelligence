<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoEstudioResource\Pages\ListTipoEstudioResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\TipoEstudio;

class TipoEstudioResource extends CatalogoSuelosResource
{
    protected static ?string $model = TipoEstudio::class;

    protected static ?string $navigationLabel = 'Tipos de estudio';

    protected static ?string $modelLabel = 'Tipos de estudio';

    protected static ?string $pluralModelLabel = 'Tipos de estudio';

    protected static ?string $slug = 'tipos-estudio';

    protected static ?int $navigationSort = 150;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'tipos-estudio';
    }

    public static function paginaListado(): string
    {
        return ListTipoEstudioResource::class;
    }
}
