<?php

namespace App\Filament\Resources\SiidPublicacions\Tables;

use App\Models\IndicadorTerritorial;
use App\Models\SiidIndicador;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class SiidPublicacionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('publicable_type')->label('Tipo')->formatStateUsing(fn (string $state): string => str_contains($state, 'Territorial') ? 'Territorial' : 'SIID')->badge(),
                TextColumn::make('indicador')->label('Indicador')->getStateUsing(fn ($record): string => $record->publicable?->etiqueta() ?? 'Registro no disponible')->searchable(['etiqueta']),
                TextColumn::make('dependencia')->label('Dependencia')->getStateUsing(fn ($record): ?string => $record->publicable instanceof IndicadorTerritorial ? ($record->publicable->dependencia?->nombre ?? $record->publicable->dependencia_fuente) : null)->placeholder('—')->toggleable(),
                TextColumn::make('estado_indicador')->label('Estado')->getStateUsing(fn ($record): ?string => $record->publicable instanceof IndicadorTerritorial ? (IndicadorTerritorial::ESTADOS[$record->publicable->estado] ?? $record->publicable->estado) : null)->badge()->placeholder('—')->toggleable(),
                TextColumn::make('datos_aprobados')->label('Datos aprobados')->getStateUsing(fn ($record): ?int => $record->publicable instanceof IndicadorTerritorial ? (int) $record->publicable->datos_aprobados_count : null)->placeholder('—')->alignCenter(),
                IconColumn::make('visible')->boolean()->label('Visible'),
                IconColumn::make('destacado')->boolean()->label('Destacado'),
                TextColumn::make('serie_publica')
                    ->label('Serie pública')
                    ->getStateUsing(fn ($record): ?string => $record->publicable instanceof IndicadorTerritorial ? ($record->mostrar_serie ? 'Visible' : 'Oculta') : null)
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'Visible' ? 'success' : 'gray')
                    ->placeholder('—'),
                TextColumn::make('tablero_tipo')->label('Tablero')->badge()->placeholder('Heredado'),
                TextColumn::make('orden')->sortable(),
            ])
            ->filters([
                SelectFilter::make('publicable_type')->label('Tipo')->options([
                    SiidIndicador::class => 'SIID',
                    IndicadorTerritorial::class => 'Territorial',
                ]),
                SelectFilter::make('visible')->options(['1' => 'Visibles', '0' => 'Ocultos']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publicar')->label('Hacer visibles')->action(fn (Collection $records) => $records->each->update(['visible' => true, 'publicado_por' => auth()->id(), 'publicado_at' => now()])),
                    BulkAction::make('ocultar')->label('Ocultar')->action(fn (Collection $records) => $records->each->update(['visible' => false])),
                ]),
            ])->defaultSort('orden');
    }
}
