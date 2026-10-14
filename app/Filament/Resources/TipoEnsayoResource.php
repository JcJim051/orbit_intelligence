<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoEnsayoResource\Pages\ListTipoEnsayoResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Models\Suelos\TipoEnsayo;
use App\Models\Suelos\UnidadMedida;

class TipoEnsayoResource extends CatalogoSuelosResource
{
    protected static ?string $model = TipoEnsayo::class;

    protected static ?string $navigationLabel = 'Tipos de ensayo';

    protected static ?string $modelLabel = 'Tipos de ensayo';

    protected static ?string $pluralModelLabel = 'Tipos de ensayo';

    protected static ?string $slug = 'tipos-ensayo';

    protected static ?int $navigationSort = 130;

    protected static array $columnas = [
        ['name' => 'codigo', 'label' => 'Código', 'tipo' => 'texto'],
        ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'texto'],
        ['name' => 'ambito', 'label' => 'Ámbito', 'tipo' => 'enum', 'opciones' => ['LABORATORIO', 'CAMPO'], 'requerido' => true],
        ['name' => 'unidad_id', 'label' => 'Unidad', 'tipo' => 'relacion', 'excel' => 'unidad', 'modelo' => UnidadMedida::class, 'requerido' => true],
        ['name' => 'rango_min', 'label' => 'Rango mínimo', 'tipo' => 'numero'],
        ['name' => 'rango_max', 'label' => 'Rango máximo', 'tipo' => 'numero'],
        ['name' => 'norma_referencia', 'label' => 'Norma', 'tipo' => 'texto'],
        ['name' => 'descripcion', 'label' => 'Descripción', 'tipo' => 'texto_largo'],
        ['name' => 'orden', 'label' => 'Orden', 'tipo' => 'numero'],
        ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'bool'],
    ];

    public static function clave(): string
    {
        return 'tipos-ensayo';
    }

    public static function paginaListado(): string
    {
        return ListTipoEnsayoResource::class;
    }
}
