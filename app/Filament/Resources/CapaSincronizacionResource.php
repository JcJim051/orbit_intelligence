<?php

namespace App\Filament\Resources;

use App\Enums\EstadoSincronizacionCapa;
use App\Filament\Resources\CapaSincronizacionResource\Pages\ListCapaSincronizaciones;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use App\Services\InteligenciaGeografica\SincronizarCapa;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CapaSincronizacionResource extends Resource
{
    protected static ?string $model = CapaSincronizacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Sincronizaciones';

    protected static ?string $modelLabel = 'sincronización';

    protected static ?string $pluralModelLabel = 'Sincronizaciones';

    protected static ?string $slug = 'sincronizaciones';

    protected static string|UnitEnum|null $navigationGroup = 'Inteligencia geográfica';

    protected static ?int $navigationSort = 80;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('capa.codigo')->label('Capa')->formatStateUsing(fn (CapaSincronizacion $record): string => $record->capa?->etiqueta() ?? ''),
                TextColumn::make('iniciado_at')->label('Inicio')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('finalizado_at')->label('Fin')->dateTime('d/m/Y H:i')->placeholder('En curso'),
                TextColumn::make('fecha_corte_detectada')->label('Fecha de corte')->dateTime('d/m/Y H:i')->placeholder('No informada'),
                IconColumn::make('cambio')->label('Cambió')->boolean(),
                TextColumn::make('entidades_agregadas')->label('Agregadas')->action(self::detalleConteo('agregadas', 'entidades nuevas reportadas por la corrida')),
                TextColumn::make('entidades_actualizadas')->label('Actualizadas')->action(self::detalleConteo('actualizadas', 'entidades modificadas reportadas por la corrida')),
                TextColumn::make('entidades_eliminadas')->label('Eliminadas')->action(self::detalleConteo('eliminadas', 'entidades retiradas reportadas por la corrida')),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (EstadoSincronizacionCapa $state): string => $state->label()),
                TextColumn::make('url_consultada')->label('URL')->limit(40)->toggleable(),
                TextColumn::make('licencia_detectada')->label('Licencia')->limit(40)->toggleable(),
                TextColumn::make('checksum')->label('Checksum')->limit(12)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mensaje_error')->label('Mensaje')->limit(60)->placeholder('—'),
                TextColumn::make('nota')->label('Nota')->limit(60)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('capa_id')->label('Capa')->relationship('capa', 'nombre')
                    ->getOptionLabelFromRecordUsing(fn (Capa $record): string => $record->etiqueta()),
                SelectFilter::make('estado')->label('Estado')->options(CapaResource::opciones(EstadoSincronizacionCapa::class)),
            ])
            ->headerActions([
                Action::make('sincronizar')->label('Sincronizar ahora')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->schema([
                        Select::make('capa_id')->label('Capa')->options(fn (): array => Capa::query()->orderBy('codigo')->get()->mapWithKeys(fn (Capa $capa): array => [$capa->id => $capa->etiqueta()])->all())->required(),
                    ])
                    ->action(function (array $data): void {
                        $capa = Capa::query()->findOrFail($data['capa_id']);
                        abort_unless(auth()->user()?->can('sincronizar', $capa), 403);

                        try {
                            $corrida = app(SincronizarCapa::class)->ejecutar($capa);
                        } catch (\InvalidArgumentException $excepcion) {
                            Notification::make()->title('Sin conector externo')->body($excepcion->getMessage())->warning()->send();

                            return;
                        }

                        Notification::make()->title($corrida->estado->label())->body($corrida->mensaje_error ?: $corrida->nota)->success()->send();
                    }),
            ])
            ->recordActions([])
            ->defaultSort('iniciado_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCapaSincronizaciones::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    private static function detalleConteo(string $nombre, string $explicacion): Action
    {
        return Action::make('detalle'.$nombre)
            ->modalHeading('Detalle del conteo')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn (Model $record): View => view('filament.inteligencia.detalle-conteo', [
                'titulo' => $explicacion,
                'cantidad' => (int) $record->{'entidades_'.$nombre},
                'lineas' => (int) $record->{'entidades_'.$nombre} > 0
                    ? ['La corrida no guarda el listado de cada entidad. Este número es el total informado.']
                    : [],
            ]))
            ->disabled(fn (Model $record): bool => (int) $record->{'entidades_'.$nombre} < 1);
    }
}
