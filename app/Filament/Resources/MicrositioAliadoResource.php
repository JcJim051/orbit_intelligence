<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioAliadoResource\Pages\ListMicrositioAliados;
use App\Models\MicrositioAliado;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class MicrositioAliadoResource extends MicrositioResource
{
    protected static ?string $model = MicrositioAliado::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Aliados';

    protected static ?string $modelLabel = 'aliado';

    protected static ?string $pluralModelLabel = 'Aliados';

    protected static ?string $slug = 'micrositio-aliados';

    protected static ?int $navigationSort = 40;

    public static function seccion(): string
    {
        return 'aliados';
    }

    public static function tieneExcel(): bool
    {
        return false;
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('nombre')->label('Nombre')->searchable(),
            TextColumn::make('url')->label('Enlace')->limit(40),
        ];
    }

    public static function campos(): array
    {
        return [
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(160)->columnSpanFull(),
            FileUpload::make('logo_path')
                ->label('Logo')
                ->disk('local')
                ->directory('micrositio/aliados')
                ->image()
                ->helperText('Si no carga un logo, el inicio muestra el nombre en una caja.')
                ->columnSpanFull(),
            TextInput::make('url')->label('Enlace')->maxLength(500)->columnSpanFull(),
            TextInput::make('orden')->label('Orden')->numeric()->default(0)->minValue(0),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMicrositioAliados::route('/'),
        ];
    }
}
