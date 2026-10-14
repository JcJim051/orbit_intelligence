<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SistemaCoordenadasResource\Pages\ListSistemaCoordenadasResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\SistemaCoordenadas;

class SistemaCoordenadasResource extends CatalogoSuelosResource
{
    protected static ?string $model = SistemaCoordenadas::class;

    protected static ?string $navigationLabel = 'Sistemas de coordenadas';

    protected static ?string $modelLabel = 'Sistemas de coordenadas';

    protected static ?string $pluralModelLabel = 'Sistemas de coordenadas';

    protected static ?string $slug = 'sistemas-coordenadas';

    protected static ?int $navigationSort = 210;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'srid', 'label' => 'SRID', 'tipo' => 'numero', 'requerido' => true],
        ['name' => 'observacion', 'label' => 'Observación', 'tipo' => 'texto_largo'],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'sistemas-coordenadas';
    }

    public static function paginaListado(): string
    {
        return ListSistemaCoordenadasResource::class;
    }
}
