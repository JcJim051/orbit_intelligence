<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EstudioSuelosResource\Pages\CreateEstudioSuelos;
use App\Filament\Resources\EstudioSuelosResource\Pages\EditEstudioSuelos;
use App\Filament\Resources\EstudioSuelosResource\Pages\ListEstudiosSuelos;
use App\Filament\Resources\EstudioSuelosResource\Pages\ViewEstudioSuelos;
use App\Filament\Resources\EstudioSuelosResource\RelationManagers\AdjuntosRelationManager;
use App\Filament\Resources\EstudioSuelosResource\RelationManagers\EnsayosRelationManager;
use App\Filament\Resources\EstudioSuelosResource\RelationManagers\EstratosRelationManager;
use App\Filament\Resources\EstudioSuelosResource\RelationManagers\ExploracionesRelationManager;
use App\Filament\Resources\EstudioSuelosResource\RelationManagers\ParametrosRelationManager;
use App\Models\Dependencia;
use App\Models\InvestmentProject;
use App\Models\Municipio;
use App\Models\Suelos\EstudioSuelos;
use App\Models\Suelos\TipoEstudio;
use App\Services\Suelos\LibroEstudiosSuelos;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class EstudioSuelosResource extends Resource
{
    protected static ?string $model = EstudioSuelos::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Estudios de suelos';

    protected static ?string $modelLabel = 'estudio de suelos';

    protected static ?string $pluralModelLabel = 'Estudios de suelos';

    protected static ?string $slug = 'estudios-suelos';

    protected static string|UnitEnum|null $navigationGroup = 'Inteligencia geográfica';

    protected static ?int $navigationSort = 100;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')->label('Código')->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('titulo')->label('Título')->required(),
            Select::make('tipo_estudio_id')->label('Tipo de estudio')->relationship('tipoEstudio', 'nombre')
                ->getOptionLabelFromRecordUsing(fn (TipoEstudio $record): string => $record->etiqueta())->required()->searchable()->preload(),
            Select::make('dependencia_id')->label('Entidad')->relationship('dependencia', 'nombre')
                ->getOptionLabelFromRecordUsing(fn (Dependencia $record): string => $record->etiqueta())->required()->searchable()->preload(),
            Select::make('investment_project_id')->label('BPIN')->relationship('proyecto', 'bpin')
                ->getOptionLabelFromRecordUsing(fn (InvestmentProject $record): string => $record->bpin.' — '.($record->name ?: 'Sin nombre'))
                ->searchable()->preload(),
            Textarea::make('sin_bpin_justificacion')->label('Justificación sin BPIN')->helperText('Obligatoria, mínimo 20 caracteres, cuando no hay BPIN.')->columnSpanFull(),
            TextInput::make('consultor_nombre')->label('Consultor')->required()->maxLength(200),
            TextInput::make('consultor_nit')->label('NIT del consultor')->maxLength(15),
            TextInput::make('ingeniero_responsable')->label('Ingeniero responsable')->maxLength(200),
            TextInput::make('matricula_profesional')->label('Matrícula profesional')->maxLength(40),
            TextInput::make('fecha_estudio')->label('Fecha del estudio')->type('date')->required(),
            Select::make('municipio_id')->label('Municipio')->relationship('municipio', 'nombre')
                ->getOptionLabelFromRecordUsing(fn (Municipio $record): string => $record->codigo_dane.' — '.$record->nombre)
                ->required()->searchable()->preload(),
            Textarea::make('descripcion_sitio')->label('Descripción del sitio')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Estudio')->formatStateUsing(fn (EstudioSuelos $record): string => $record->codigo.' — '.$record->titulo)->searchable(['codigo', 'titulo']),
                TextColumn::make('dependencia.codigo')->label('Entidad')->formatStateUsing(fn (EstudioSuelos $record): string => $record->dependencia?->etiqueta() ?? ''),
                TextColumn::make('estadoValidacion.codigo')->label('Estado')->badge()->formatStateUsing(fn (EstudioSuelos $record): string => $record->estadoValidacion?->etiqueta() ?? ''),
                TextColumn::make('exploraciones_count')->counts('exploraciones')->label('Exploraciones')
                    ->action(self::detalleConteo('exploraciones', 'Exploraciones del estudio', fn (EstudioSuelos $record): array => $record->exploraciones()->with('tipoExploracion')->get()->map(fn ($exploracion): string => $exploracion->codigo.' — '.($exploracion->tipoExploracion?->nombre ?? ''))->all())),
                TextColumn::make('estratos_count')->counts('estratos')->label('Estratos')
                    ->action(self::detalleConteo('estratos', 'Estratos del estudio', fn (EstudioSuelos $record): array => $record->estratos()->with('exploracion')->get()->map(fn ($estrato): string => ($estrato->exploracion?->codigo ?? '').' — '.$estrato->profundidad_desde_m.' a '.$estrato->profundidad_hasta_m.' m')->all())),
                TextColumn::make('ensayos_count')->counts('ensayos')->label('Ensayos')
                    ->action(self::detalleConteo('ensayos', 'Ensayos del estudio', fn (EstudioSuelos $record): array => $record->ensayos()->with('tipoEnsayo')->get()->map(fn ($ensayo): string => ($ensayo->tipoEnsayo?->etiqueta() ?? '').' — '.$ensayo->valor)->all())),
            ])
            ->headerActions([
                Action::make('plantilla')->label('Descargar plantilla')->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (): string => route('inteligencia.estudios.plantilla'))
                    ->visible(fn (): bool => auth()->user()?->can('export', EstudioSuelos::class) ?? false),
                Action::make('importar')->label('Importar desde Excel')->icon(Heroicon::OutlinedArrowUpTray)
                    ->visible(fn (): bool => auth()->user()?->can('import', EstudioSuelos::class) ?? false)
                    ->authorize(fn (): bool => auth()->user()?->can('import', EstudioSuelos::class) ?? false)
                    ->schema([
                        FileUpload::make('archivo')->label('Libro de estudios')->required()->disk('local')->directory('imports')->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]),
                    ])
                    ->action(function (array $data): void {
                        $ruta = Storage::disk('local')->path($data['archivo']);
                        app(LibroEstudiosSuelos::class)->importar($ruta, auth()->user());
                        Storage::disk('local')->delete($data['archivo']);
                        Notification::make()->title('Estudios importados')->success()->send();
                    }),
            ])
            ->defaultSort('fecha_estudio', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ExploracionesRelationManager::class,
            EstratosRelationManager::class,
            EnsayosRelationManager::class,
            ParametrosRelationManager::class,
            AdjuntosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEstudiosSuelos::route('/'),
            'create' => CreateEstudioSuelos::route('/crear'),
            'view' => ViewEstudioSuelos::route('/{record}'),
            'edit' => EditEstudioSuelos::route('/{record}/editar'),
        ];
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof EstudioSuelos ? $record->codigo.' — '.$record->titulo : null;
    }

    /**
     * @param  callable(EstudioSuelos): list<string>  $lineas
     */
    private static function detalleConteo(string $nombre, string $titulo, callable $lineas): Action
    {
        return Action::make('detalle'.$nombre)
            ->modalHeading($titulo)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn (EstudioSuelos $record) => view('filament.suelos.detalle-registros', [
                'titulo' => 'Este número suma los registros vinculados al estudio.',
                'lineas' => $lineas($record),
            ]))
            ->disabled(fn (EstudioSuelos $record): bool => (int) $record->{$nombre.'_count'} < 1);
    }
}
