<?php

namespace App\Filament\Resources\IndicadorPublicos;

use App\Filament\Resources\IndicadorPublicos\Pages\ManageIndicadorPublicos;
use App\Models\Dashboard;
use App\Models\MicrositioIndicador;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class IndicadorPublicoResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = MicrositioIndicador::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Indicadores del micrositio';

    protected static ?string $modelLabel = 'indicador';

    protected static ?string $pluralModelLabel = 'Indicadores y tableros';

    protected static ?string $slug = 'indicadores-publicos';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['tema', 'dashboard', 'dataSource.currentVersion']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codigo')
                    ->label('Código')
                    ->required()
                    ->maxLength(40)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                TextInput::make('nombre')
                    ->label('Indicador')
                    ->required()
                    ->maxLength(255),
                Select::make('micrositio_tema_id')
                    ->label('Eje temático')
                    ->relationship('tema', 'nombre', modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('orden')->orderBy('nombre'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),
                Select::make('dashboard_id')
                    ->label('Tablero')
                    ->relationship('dashboard', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'))
                    ->getOptionLabelFromRecordUsing(fn (Dashboard $record): string => $record->name.($record->superset_embedded_uuid ? ' · listo para embeber' : ' · pendiente de sincronizar'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),
                Textarea::make('descripcion')
                    ->label('Descripción pública')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('conjunto_datos')
                    ->label('Identificador del conjunto de datos para CSV')
                    ->maxLength(40)
                    ->helperText('Opcional. Se usa únicamente cuando el indicador ofrece una descarga CSV.'),
                TextInput::make('orden')
                    ->label('Orden')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->required(),
                Toggle::make('es_ejemplo')
                    ->label('Contenido de demostración')
                    ->helperText('La visibilidad pública se administra únicamente desde Micrositio GIEE → Indicadores.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable()->sortable(),
                TextColumn::make('nombre')->label('Indicador')->searchable()->wrap(),
                TextColumn::make('tema.nombre')->label('Eje temático')->searchable()->sortable(),
                TextColumn::make('dashboard.name')->label('Tablero')->searchable()->placeholder('Sin tablero')->wrap(),
                IconColumn::make('dashboard.superset_embedded_uuid')
                    ->label('Superset')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedClock),
                IconColumn::make('es_publico')->label('Visible')->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('serie')
                    ->label('Serie de datos')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->url(fn (MicrositioIndicador $record): string => static::getUrl('series', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('orden');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageIndicadorPublicos::route('/'),
            'series' => Pages\ManageIndicatorSeries::route('/{record}/serie'),
        ];
    }
}
