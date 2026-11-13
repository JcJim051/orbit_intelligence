<?php

namespace App\Filament\Resources\SiidSincronizacions\Tables;

use App\Models\SiidSincronizacion;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SiidSincronizacionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('iniciado_at')->label('Ejecución')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('origen')->badge(),
                TextColumn::make('estado')->badge(),
                TextColumn::make('leidos')->label('Leídos'),
                TextColumn::make('creados')->label('Nuevos'),
                TextColumn::make('actualizados'),
                TextColumn::make('inactivados'),
                TextColumn::make('conflictos')->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('detalle')->label('Ver detalle')->modalHeading('Detalle de la sincronización')->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (SiidSincronizacion $record) => view('filament.siid.sincronizacion-detalle', ['record' => $record->load('detalles')])),
            ])->defaultSort('iniciado_at', 'desc');
    }
}
