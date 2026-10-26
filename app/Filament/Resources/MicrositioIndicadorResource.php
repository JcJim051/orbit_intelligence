<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioIndicadorResource\Pages\ListMicrositioIndicadores;
use App\Models\MicrositioIndicador;
use App\Models\MicrositioTema;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class MicrositioIndicadorResource extends MicrositioResource
{
    protected static ?string $model = MicrositioIndicador::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $navigationLabel = 'Indicadores';

    protected static ?string $modelLabel = 'indicador';

    protected static ?string $pluralModelLabel = 'Indicadores del micrositio';

    protected static ?string $slug = 'micrositio-indicadores';

    protected static ?int $navigationSort = 50;

    public static function seccion(): string
    {
        return 'indicadores';
    }

    public static function urlVistaPrevia(): string
    {
        return route('micrositio.indicadores', ['preview' => 1]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('tema');
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('codigo')
                ->label('Indicador')
                ->state(fn (MicrositioIndicador $record): string => $record->etiqueta())
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where(function (Builder $consulta) use ($search): void {
                        $consulta->where('codigo', 'like', '%'.$search.'%')->orWhere('nombre', 'like', '%'.$search.'%');
                    });
                }),
            TextColumn::make('tema.nombre')->label('Tema'),
            IconColumn::make('es_publico')->label('Público')->boolean(),
            IconColumn::make('es_ejemplo')->label('Ejemplo')->boolean(),
            TextColumn::make('dashboard_uuid')->label('Tablero')->limit(13)->placeholder('Sin UUID'),
        ];
    }

    public static function campos(): array
    {
        return [
            TextInput::make('codigo')->label('Código')->required()->maxLength(40),
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(180),
            Select::make('micrositio_tema_id')
                ->label('Tema')
                ->options(fn (): array => MicrositioTema::query()->orderBy('orden')->get()->mapWithKeys(fn (MicrositioTema $tema): array => [$tema->id => $tema->etiqueta()])->all())
                ->required()
                ->native(false),
            TextInput::make('dashboard_uuid')
                ->label('UUID del tablero en Superset')
                ->maxLength(36)
                ->rule('nullable')
                ->rule('uuid'),
            Textarea::make('descripcion')->label('Descripción')->required()->rows(3)->columnSpanFull(),
            Select::make('conjunto_datos')
                ->label('Conjunto de datos para el CSV')
                ->options(['pasiva' => 'Pasiva de Hacienda'])
                ->placeholder('Sin conjunto de datos')
                ->native(false),
            TextInput::make('orden')->label('Orden')->numeric()->default(0)->minValue(0),
            Toggle::make('es_publico')->label('Visible en el micrositio público'),
            Toggle::make('es_ejemplo')->label('Marcar como dato de ejemplo'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMicrositioIndicadores::route('/'),
        ];
    }
}
