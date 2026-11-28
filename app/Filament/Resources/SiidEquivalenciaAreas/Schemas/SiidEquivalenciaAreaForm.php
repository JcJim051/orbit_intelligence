<?php

namespace App\Filament\Resources\SiidEquivalenciaAreas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SiidEquivalenciaAreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('texto_origen')->label('Texto recibido')->required()->maxLength(255)->unique(ignoreRecord: true),
                Select::make('area_tematica_id')->label('Área canónica')->relationship('area', 'nombre')->searchable()->preload()->required(),
                Toggle::make('activo')->default(true),
            ]);
    }
}
