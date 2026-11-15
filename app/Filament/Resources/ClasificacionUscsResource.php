<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClasificacionUscsResource\Pages\ListClasificacionUscsResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\ClasificacionUscs;

class ClasificacionUscsResource extends CatalogoSuelosResource
{
    protected static ?string $model = ClasificacionUscs::class;

    protected static ?string $navigationLabel = 'Clasificación USCS';

    protected static ?string $modelLabel = 'Clasificación USCS';

    protected static ?string $pluralModelLabel = 'Clasificaciones USCS';

    protected static ?string $slug = 'clasificacion-uscs';

    protected static ?int $navigationSort = 110;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'grupo', 'label' => 'Grupo', 'tipo' => 'enum', 'opciones' => ['GRUESO', 'FINO', 'ORGANICO', 'DOBLE'], 'requerido' => true],
        ['name' => 'descripcion', 'label' => 'Descripción', 'tipo' => 'texto_largo'],
        ['name' => 'orden', 'label' => 'Orden', 'tipo' => 'numero'],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'uscs';
    }

    public static function paginaListado(): string
    {
        return ListClasificacionUscsResource::class;
    }
}
