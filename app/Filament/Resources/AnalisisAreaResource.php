<?php

namespace App\Filament\Resources;

use App\Enums\EstadoAnalisisArea;
use App\Enums\ModoAnalisis;
use App\Filament\Resources\AnalisisAreaResource\Pages\CreateAnalisisArea;
use App\Filament\Resources\AnalisisAreaResource\Pages\ListAnalisisAreas;
use App\Filament\Resources\AnalisisAreaResource\Pages\MapaAnalisis;
use App\Filament\Resources\AnalisisAreaResource\Pages\ViewAnalisisArea;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InvestmentProject;
use App\Services\InteligenciaGeografica\InformeAnalisis;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AnalisisAreaResource extends Resource
{
    protected static ?string $model = AnalisisArea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static ?string $navigationLabel = 'Análisis de área';

    protected static ?string $modelLabel = 'análisis de área';

    protected static ?string $pluralModelLabel = 'Análisis de área';

    protected static ?string $slug = 'analisis-area';

    protected static string|UnitEnum|null $navigationGroup = 'Inteligencia geográfica';

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('investment_project_id')->label('BPIN')->relationship('proyecto', 'bpin')
                ->getOptionLabelFromRecordUsing(fn (InvestmentProject $record): string => $record->bpin.' — '.($record->name ?: 'Sin nombre'))
                ->searchable()->preload(),
            Select::make('modo')->label('Modo')->options(CapaResource::opciones(ModoAnalisis::class))->default(ModoAnalisis::Normal->value)->required()
                ->helperText('Oficial consulta la fuente en vivo. Si la fuente no responde, se usa la copia local y queda la advertencia.'),
            FileUpload::make('archivo')->label('KMZ, KML o GeoJSON')->disk('local')->directory('analisis')->preserveFilenames()
                ->acceptedFileTypes(['application/vnd.google-earth.kml+xml', 'application/vnd.google-earth.kmz', 'application/geo+json', 'application/json', 'application/octet-stream']),
            ViewField::make('geojson')->label('Polígono')->view('filament.inteligencia.mapa-poligono')
                ->viewData(fn (ViewField $campo): array => [
                    'rutaGeojson' => (string) $campo->getStatePath(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('modo')->label('Modo')->formatStateUsing(fn (ModoAnalisis $state): string => $state->label()),
            TextEntry::make('estado')->label('Estado')->formatStateUsing(fn (EstadoAnalisisArea $state): string => $state->label()),
            TextEntry::make('proyecto.bpin')->label('BPIN')->placeholder('Sin proyecto')
                ->formatStateUsing(fn ($state, AnalisisArea $record): string => $record->proyecto ? $record->proyecto->bpin.' — '.($record->proyecto->name ?: 'Sin nombre') : 'Sin proyecto'),
            ViewEntry::make('informe')->view('filament.inteligencia.analisis')->columnSpanFull()
                ->viewData(fn (AnalisisArea $record): array => [
                    'analisis' => $record,
                    'geojson' => $record->geoJson4326(),
                    'filas' => app(InformeAnalisis::class)->filas($record),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('modo')->label('Modo')->formatStateUsing(fn (ModoAnalisis $state): string => $state->label()),
                TextColumn::make('proyecto.bpin')->label('BPIN')->placeholder('Sin proyecto')
                    ->formatStateUsing(fn ($state, AnalisisArea $record): string => $record->proyecto ? $record->proyecto->bpin.' — '.($record->proyecto->name ?: 'Sin nombre') : 'Sin proyecto'),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (EstadoAnalisisArea $state): string => $state->label()),
                TextColumn::make('resultados_count')->label('Capas con resultado')->counts('resultados')
                    ->action(
                        Action::make('detalleResultados')
                            ->modalHeading('Capas con resultado')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Cerrar')
                            ->modalContent(fn (AnalisisArea $record) => view('filament.suelos.detalle-registros', [
                                'titulo' => 'Este número suma las capas que ya tienen un resultado atribuido.',
                                'lineas' => $record->resultados()->with('capa')->orderBy('id')->get()->map(
                                    fn ($resultado): string => ($resultado->capa?->etiqueta() ?? 'Capa').': conteo '.($resultado->conteo ?? 'sin dato')
                                )->all(),
                            ]))
                            ->disabled(fn (AnalisisArea $record): bool => (int) $record->resultados_count < 1),
                    ),
            ])
            ->defaultSort('fecha', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => MapaAnalisis::route('/'),
            'historial' => ListAnalisisAreas::route('/historial'),
            'create' => CreateAnalisisArea::route('/crear'),
            'view' => ViewAnalisisArea::route('/{record}'),
        ];
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof AnalisisArea ? 'Análisis '.$record->fecha?->format('d/m/Y H:i') : null;
    }
}
