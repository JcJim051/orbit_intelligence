<?php

namespace App\Filament\Resources;

use App\Enums\EstadoFrescuraCapa;
use App\Enums\FrecuenciaActualizacionCapa;
use App\Enums\TipoAccesoCapa;
use App\Filament\Resources\CapaResource\Pages\CreateCapa;
use App\Filament\Resources\CapaResource\Pages\EditCapa;
use App\Filament\Resources\CapaResource\Pages\ListCapas;
use App\Filament\Resources\CapaResource\Pages\ViewCapa;
use App\Filament\Resources\CapaResource\RelationManagers\SincronizacionesRelationManager;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\Fuente;
use App\Services\InteligenciaGeografica\CapaExcel;
use App\Services\InteligenciaGeografica\RegistrarSincronizacionPendiente;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class CapaResource extends Resource
{
    protected static ?string $model = Capa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Capas';

    protected static ?string $modelLabel = 'capa';

    protected static ?string $pluralModelLabel = 'Capas';

    protected static ?string $slug = 'capas';

    protected static string|UnitEnum|null $navigationGroup = 'Inteligencia geográfica';

    protected static ?int $navigationSort = 70;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')->label('Código')->required()->maxLength(80)->unique(ignoreRecord: true),
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(200),
            Select::make('fuente_id')->label('Entidad fuente')->relationship('fuente', 'nombre')
                ->getOptionLabelFromRecordUsing(fn (Fuente $record): string => $record->etiqueta())
                ->searchable()->preload()->required(),
            Select::make('tipo_acceso')->label('Tipo de acceso')->options(self::opciones(TipoAccesoCapa::class))->required(),
            Select::make('frecuencia_actualizacion')->label('Frecuencia')->options(self::opciones(FrecuenciaActualizacionCapa::class))->required(),
            TextInput::make('ttl_horas')->label('TTL (horas)')->numeric()->minValue(0)->required(),
            TextInput::make('licencia')->label('Licencia')->maxLength(500),
            Textarea::make('cita_fuente')->label('Cita de la fuente')->required()->columnSpanFull(),
            Textarea::make('pregunta')->label('Pregunta del informe')->required()->columnSpanFull(),
            TextInput::make('url_servicio')->label('URL del servicio')->maxLength(500),
            TextInput::make('layer_id')->label('Identificador de capa')->maxLength(80),
            Textarea::make('observacion')->label('Observación')->columnSpanFull(),
            Toggle::make('activa')->label('Activa')->default(true),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('codigo')->label('Capa')->formatStateUsing(fn (Capa $record): string => $record->etiqueta()),
            TextEntry::make('fuente.codigo')->label('Entidad fuente')->formatStateUsing(fn (Capa $record): string => $record->fuente?->etiqueta() ?? ''),
            TextEntry::make('cita_fuente')->label('Cita de la fuente')->columnSpanFull(),
            TextEntry::make('licencia')->label('Licencia')->placeholder('Pendiente de confirmación'),
            TextEntry::make('url_servicio')->label('URL')->placeholder('URL pendiente de confirmación'),
            TextEntry::make('pregunta')->label('Pregunta')->columnSpanFull(),
            TextEntry::make('observacion')->label('Observación')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Capa')->formatStateUsing(fn (Capa $record): string => $record->etiqueta())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $inner) use ($search): void {
                            $inner->where('codigo', 'ilike', "%{$search}%")->orWhere('nombre', 'ilike', "%{$search}%");
                        });
                    })
                    ->sortable(),
                TextColumn::make('fuente.codigo')->label('Entidad fuente')->formatStateUsing(fn (Capa $record): string => $record->fuente?->etiqueta() ?? ''),
                TextColumn::make('tipo_acceso')->label('Tipo de acceso')->formatStateUsing(fn (TipoAccesoCapa $state): string => $state->label()),
                TextColumn::make('frecuencia_actualizacion')->label('Frecuencia')->formatStateUsing(fn (FrecuenciaActualizacionCapa $state): string => $state->label()),
                TextColumn::make('estado_frescura')->label('Estado de frescura')->badge()
                    ->formatStateUsing(fn (EstadoFrescuraCapa $state): string => $state->label())
                    ->color(fn (EstadoFrescuraCapa $state): string => match ($state) {
                        EstadoFrescuraCapa::Vigente => 'success',
                        EstadoFrescuraCapa::Error => 'danger',
                        EstadoFrescuraCapa::PosiblementeDesactualizada => 'warning',
                    }),
                TextColumn::make('fecha_corte_fuente')->label('Fecha de corte fuente')->dateTime('d/m/Y H:i')->placeholder('Sin fecha'),
                TextColumn::make('fecha_ultima_sincronizacion')->label('Última sincronización')->dateTime('d/m/Y H:i')->placeholder('Sin sincronizar'),
                TextColumn::make('licencia')->label('Licencia')->placeholder('Pendiente de confirmación')->limit(40),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->headerActions([
                Action::make('descargarTodo')->label('Descargar todo')->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (): string => route('inteligencia.capas.exportar'))
                    ->visible(fn (): bool => auth()->user()?->can('export', Capa::class) ?? false),
                Action::make('plantilla')->label('Descargar plantilla')->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (): string => route('inteligencia.capas.plantilla'))
                    ->visible(fn (): bool => auth()->user()?->can('export', Capa::class) ?? false),
                Action::make('importar')->label('Importar desde Excel')->icon(Heroicon::OutlinedArrowUpTray)
                    ->visible(fn (): bool => auth()->user()?->can('import', Capa::class) ?? false)
                    ->authorize(fn (): bool => auth()->user()?->can('import', Capa::class) ?? false)
                    ->schema([
                        FileUpload::make('archivo')->label('Archivo de Excel')->required()->disk('local')->directory('imports')->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]),
                    ])
                    ->action(function (array $data): void {
                        $ruta = Storage::disk('local')->path($data['archivo']);
                        app(CapaExcel::class)->importar($ruta);
                        Storage::disk('local')->delete($data['archivo']);
                        Notification::make()->title('Capas importadas')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('sincronizar')->label('Sincronizar ahora')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Capa $record): bool => auth()->user()?->can('sincronizar', $record) ?? false)
                    ->authorize(fn (Capa $record): bool => auth()->user()?->can('sincronizar', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalDescription('El conector de descarga todavía no está disponible. Se registrará una corrida que lo indica.')
                    ->action(function (Capa $record): void {
                        app(RegistrarSincronizacionPendiente::class)->registrar($record);
                        Notification::make()->title('Sincronización registrada')->body('Conector pendiente.')->warning()->send();
                    }),
                EditAction::make()->label('Editar'),
            ])
            ->defaultSort('codigo');
    }

    public static function getRelations(): array
    {
        return [
            SincronizacionesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCapas::route('/'),
            'create' => CreateCapa::route('/crear'),
            'view' => ViewCapa::route('/{record}'),
            'edit' => EditCapa::route('/{record}/editar'),
        ];
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof Capa ? $record->etiqueta() : null;
    }

    /**
     * @param  class-string  $enum
     * @return array<string, string>
     */
    public static function opciones(string $enum): array
    {
        return collect($enum::cases())->mapWithKeys(fn (BackedEnum $caso): array => [$caso->value => method_exists($caso, 'label') ? $caso->label() : $caso->value])->all();
    }
}
