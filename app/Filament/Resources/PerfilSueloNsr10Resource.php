<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerfilSueloNsr10Resource\Pages\ListPerfilSueloNsr10Resource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\PerfilSueloNsr10;

class PerfilSueloNsr10Resource extends CatalogoSuelosResource
{
    protected static ?string $model = PerfilSueloNsr10::class;

    protected static ?string $navigationLabel = 'Perfiles NSR-10';

    protected static ?string $modelLabel = 'Perfiles NSR-10';

    protected static ?string $pluralModelLabel = 'Perfiles NSR-10';

    protected static ?string $slug = 'perfiles-nsr10';

    protected static ?int $navigationSort = 200;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'vs30_min_m_s', 'label' => 'Vs30 mínimo', 'tipo' => 'numero'],
        ['name' => 'vs30_max_m_s', 'label' => 'Vs30 máximo', 'tipo' => 'numero'],
        ['name' => 'descripcion', 'label' => 'Descripción', 'tipo' => 'texto_largo'],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'perfiles-nsr10';
    }

    public static function paginaListado(): string
    {
        return ListPerfilSueloNsr10Resource::class;
    }
}
