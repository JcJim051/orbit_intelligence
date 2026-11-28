<?php

namespace App\Filament\Resources\SiidIndicadors\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SiidIndicadorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable()->sortable(),
                TextColumn::make('nombre')->label('Indicador')->searchable()->wrap()->limit(90),
                TextColumn::make('eje.nombre')->label('Eje')->sortable(),
                TextColumn::make('area.nombre')->label('Área temática')->placeholder('Sin equivalencia')->wrap(),
                TextColumn::make('tipo_tablero')->label('Tablero')->badge()->placeholder('Sin tablero'),
                IconColumn::make('activo')->label('Activo')->boolean(),
                IconColumn::make('publicacion.visible')->label('Visible')->boolean(),
                TextColumn::make('sincronizado_at')->label('Sincronizado')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('eje_id')->label('Eje')->relationship('eje', 'nombre'),
                SelectFilter::make('area_tematica_id')->label('Área')->relationship('area', 'nombre')->searchable()->preload(),
                SelectFilter::make('activo')->options(['1' => 'Activos', '0' => 'Inactivos']),
            ]);
    }
}
