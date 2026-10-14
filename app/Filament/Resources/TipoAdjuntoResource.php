<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoAdjuntoResource\Pages\ListTipoAdjuntoResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\TipoAdjunto;

class TipoAdjuntoResource extends CatalogoSuelosResource
{
    protected static ?string $model = TipoAdjunto::class;

    protected static ?string $navigationLabel = 'Tipos de adjunto';

    protected static ?string $modelLabel = 'Tipos de adjunto';

    protected static ?string $pluralModelLabel = 'Tipos de adjunto';

    protected static ?string $slug = 'tipos-adjunto';

    protected static ?int $navigationSort = 170;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'extensiones_permitidas', 'label' => 'Extensiones', 'tipo' => 'texto', 'requerido' => true],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'tipos-adjunto';
    }

    public static function paginaListado(): string
    {
        return ListTipoAdjuntoResource::class;
    }
}
