<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioCifraResource\Pages\ListMicrositioCifras;
use App\Models\MicrositioCifra;
use App\Services\Micrositio\ContadoresMicrositio;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

class MicrositioCifraResource extends MicrositioResource
{
    protected static ?string $model = MicrositioCifra::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Cifras clave';

    protected static ?string $modelLabel = 'cifra clave';

    protected static ?string $pluralModelLabel = 'Cifras clave';

    protected static ?string $slug = 'micrositio-cifras';

    protected static ?int $navigationSort = 30;

    public static function seccion(): string
    {
        return 'cifras';
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('valor_actual')
                ->label('Valor actual')
                ->state(function (MicrositioCifra $record): string {
                    if (($record->origen ?: 'manual') === 'manual') {
                        return (string) $record->valor;
                    }

                    $calculado = app(ContadoresMicrositio::class)->valor((string) $record->origen, (string) ($record->criterio ?: 'activos'));

                    return $calculado === null ? (string) $record->valor : (string) $calculado;
                }),
            TextColumn::make('origen')
                ->label('Origen')
                ->badge()
                ->formatStateUsing(fn (?string $state): string => ($state ?: 'manual') === 'manual' ? 'Manual' : 'Dinámico'),
            TextColumn::make('etiqueta')->label('Etiqueta')->limit(40),
            TextColumn::make('descripcion')->label('Descripción')->limit(60)->searchable(),
            TextColumn::make('fuente')->label('Fuente')->limit(40),
            TextColumn::make('fecha_corte')->label('Fecha de corte')->date('d/m/Y'),
            IconColumn::make('es_ejemplo')->label('Ejemplo')->boolean(),
        ];
    }

    public static function campos(): array
    {
        return [
            Select::make('origen')
                ->label('Origen')
                ->options([
                    'manual' => 'Manual',
                    'indicadores_sectoriales' => 'Indicadores Sectoriales (SIID)',
                    'indicadores_territoriales' => 'Indicadores Territoriales (Propios)',
                    'politicas_publicas' => 'Políticas Públicas',
                ])
                ->default('manual')
                ->required()
                ->live()
                ->native(false),
            Select::make('criterio')
                ->label('Criterio')
                ->options([
                    'activos' => 'Activos',
                    'publicados_micrositio' => 'Publicados en el micrositio',
                ])
                ->default('activos')
                ->visible(fn (Get $get): bool => filled($get('origen')) && $get('origen') !== 'manual')
                ->required(fn (Get $get): bool => filled($get('origen')) && $get('origen') !== 'manual')
                ->native(false),
            TextInput::make('etiqueta')->label('Etiqueta')->maxLength(120)->helperText('Texto bajo la cifra. Si se deja vacío se usa la descripción.'),
            TextInput::make('valor')
                ->label(fn (Get $get): string => ($get('origen') ?: 'manual') === 'manual' ? 'Valor' : 'Valor de respaldo')
                ->required()
                ->maxLength(80),
            DatePicker::make('fecha_corte')
                ->label('Fecha de corte')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y')
                ->helperText(fn (Get $get): ?string => ($get('origen') ?: 'manual') === 'manual' ? null : 'Con origen dinámico el número se calcula al consultar. Esta fecha queda como referencia.'),
            TextInput::make('descripcion')->label('Descripción')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('fuente')->label('Fuente')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('orden')->label('Orden')->numeric()->default(0)->minValue(0),
            Toggle::make('es_ejemplo')->label('Marcar como dato de ejemplo'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMicrositioCifras::route('/'),
        ];
    }
}
