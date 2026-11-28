<?php

namespace App\Filament\Resources\SiidAreaTematicas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SiidAreaTematicaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('eje_id')->relationship('eje', 'nombre')->required()->preload()->native(false),
                TextInput::make('codigo')->maxLength(60),
                TextInput::make('nombre')->required()->maxLength(200),
                TextInput::make('slug')->required()->maxLength(220)->unique(ignoreRecord: true),
                TextInput::make('orden')->numeric()->minValue(0)->default(0)->required(),
                Toggle::make('activo')->default(true),
            ]);
    }
}
