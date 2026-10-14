<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MetodoCoordenadasResource\Pages\ListMetodoCoordenadasResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\MetodoCoordenadas;

class MetodoCoordenadasResource extends CatalogoSuelosResource
{
    protected static ?string $model = MetodoCoordenadas::class;

    protected static ?string $navigationLabel = 'Métodos de coordenadas';

    protected static ?string $modelLabel = 'Métodos de coordenadas';

    protected static ?string $pluralModelLabel = 'Métodos de coordenadas';

    protected static ?string $slug = 'metodos-coordenadas';

    protected static ?int $navigationSort = 190;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'precision_estimada_m', 'label' => 'Precisión estimada (m)', 'tipo' => 'numero'],
        ['name' => 'descripcion', 'label' => 'Descripción', 'tipo' => 'texto_largo'],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'metodos-coordenadas';
    }

    public static function paginaListado(): string
    {
        return ListMetodoCoordenadasResource::class;
    }
}
