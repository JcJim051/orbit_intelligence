<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoCimentacionResource\Pages\ListTipoCimentacionResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\TipoCimentacion;

class TipoCimentacionResource extends CatalogoSuelosResource
{
    protected static ?string $model = TipoCimentacion::class;

    protected static ?string $navigationLabel = 'Tipos de cimentación';

    protected static ?string $modelLabel = 'Tipos de cimentación';

    protected static ?string $pluralModelLabel = 'Tipos de cimentación';

    protected static ?string $slug = 'tipos-cimentacion';

    protected static ?int $navigationSort = 180;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'descripcion', 'label' => 'Descripción', 'tipo' => 'texto_largo'],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'tipos-cimentacion';
    }

    public static function paginaListado(): string
    {
        return ListTipoCimentacionResource::class;
    }
}
