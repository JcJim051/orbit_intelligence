<?php

namespace App\Filament\Resources\CapaResource\RelationManagers;

use App\Enums\EstadoSincronizacionCapa;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class SincronizacionesRelationManager extends RelationManager
{
    protected static string $relationship = 'sincronizaciones';

    protected static ?string $title = 'Historial de sincronización';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('iniciado_at')->label('Inicio')->dateTime('d/m/Y H:i'),
                TextColumn::make('finalizado_at')->label('Fin')->dateTime('d/m/Y H:i')->placeholder('En curso'),
                TextColumn::make('fecha_corte_detectada')->label('Fecha de corte detectada')->dateTime('d/m/Y H:i')->placeholder('No informada'),
                IconColumn::make('cambio')->label('Cambió')->boolean(),
                TextColumn::make('entidades_agregadas')->label('Agregadas')->action($this->detalleConteo('agregadas', 'entidades nuevas reportadas por la corrida')),
                TextColumn::make('entidades_actualizadas')->label('Actualizadas')->action($this->detalleConteo('actualizadas', 'entidades modificadas reportadas por la corrida')),
                TextColumn::make('entidades_eliminadas')->label('Eliminadas')->action($this->detalleConteo('eliminadas', 'entidades retiradas reportadas por la corrida')),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (EstadoSincronizacionCapa $state): string => $state->label()),
                TextColumn::make('mensaje_error')->label('Mensaje')->placeholder('—')->limit(80),
            ])
            ->defaultSort('iniciado_at', 'desc')
            ->headerActions([])
            ->recordActions([]);
    }

    private function detalleConteo(string $nombre, string $explicacion): Action
    {
        return Action::make('detalle'.$nombre)
            ->modalHeading('Detalle del conteo')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn (Model $record): View => view('filament.inteligencia.detalle-conteo', [
                'titulo' => $explicacion,
                'cantidad' => (int) $record->{'entidades_'.$nombre},
                'lineas' => $record->{'entidades_'.$nombre} > 0
                    ? ['La corrida no guarda el listado de cada entidad. Este número es el total informado.']
                    : [],
            ]))
            ->disabled(fn (Model $record): bool => (int) $record->{'entidades_'.$nombre} < 1);
    }
}
