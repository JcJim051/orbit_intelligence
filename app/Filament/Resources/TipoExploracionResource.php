<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoExploracionResource\Pages\ListTipoExploracionResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\TipoExploracion;

class TipoExploracionResource extends CatalogoSuelosResource
{
    protected static ?string $model = TipoExploracion::class;

    protected static ?string $navigationLabel = 'Tipos de exploración';

    protected static ?string $modelLabel = 'Tipos de exploración';

    protected static ?string $pluralModelLabel = 'Tipos de exploración';

    protected static ?string $slug = 'tipos-exploracion';

    protected static ?int $navigationSort = 160;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'descripcion', 'label' => 'Descripción', 'tipo' => 'texto_largo'],
        ['name' => 'orden', 'label' => 'Orden', 'tipo' => 'numero'],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'tipos-exploracion';
    }

    public static function paginaListado(): string
    {
        return ListTipoExploracionResource::class;
    }
}
