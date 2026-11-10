<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioCifraResource\Pages\ListMicrositioCifras;
use App\Models\MicrositioCifra;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
            TextColumn::make('valor')->label('Valor')->searchable(),
            TextColumn::make('descripcion')->label('Descripción')->limit(60)->searchable(),
            TextColumn::make('fuente')->label('Fuente')->limit(40),
            TextColumn::make('fecha_corte')->label('Fecha de corte')->date('d/m/Y'),
            IconColumn::make('es_ejemplo')->label('Ejemplo')->boolean(),
        ];
    }

    public static function campos(): array
    {
        return [
            TextInput::make('valor')->label('Valor')->required()->maxLength(80),
            DatePicker::make('fecha_corte')->label('Fecha de corte')->required()->native(false)->displayFormat('d/m/Y'),
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
