<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioTextoInicioResource\Pages\ListMicrositioTextosInicio;
use App\Models\MicrositioTextoInicio;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class MicrositioTextoInicioResource extends MicrositioResource
{
    protected static ?string $model = MicrositioTextoInicio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $navigationLabel = 'Textos del inicio';

    protected static ?string $modelLabel = 'textos del inicio';

    protected static ?string $pluralModelLabel = 'Textos del inicio';

    protected static ?string $slug = 'micrositio-textos-inicio';

    protected static ?int $navigationSort = 45;

    public static function seccion(): string
    {
        return 'textos_inicio';
    }

    public static function tieneExcel(): bool
    {
        return false;
    }

    public static function puedeCrear(): bool
    {
        return MicrositioTextoInicio::query()->doesntExist();
    }

    public static function columnaOrden(): string
    {
        return 'id';
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('ultimos_titulo')->label('Lo último')->limit(40),
            TextColumn::make('publicaciones_titulo')->label('Publicaciones')->limit(40),
            TextColumn::make('aliados_titulo')->label('Aliados')->limit(40),
        ];
    }

    public static function campos(): array
    {
        return [
            TextInput::make('ultimos_titulo')->label('Título de lo último')->required()->maxLength(180)->columnSpanFull(),
            TextInput::make('ultimos_bajada')->label('Bajada de lo último')->maxLength(255)->columnSpanFull(),
            TextInput::make('ultimos_enlace')->label('Enlace de lo último')->maxLength(80),
            TextInput::make('destacados_titulo')->label('Título de destacados')->required()->maxLength(180)->columnSpanFull(),
            TextInput::make('destacados_bajada')->label('Bajada de destacados')->maxLength(255)->columnSpanFull(),
            TextInput::make('destacados_enlace')->label('Enlace de destacados')->maxLength(80),
            TextInput::make('publicaciones_titulo')->label('Título de publicaciones')->required()->maxLength(180)->columnSpanFull(),
            TextInput::make('publicaciones_bajada')->label('Bajada de publicaciones')->maxLength(255)->columnSpanFull(),
            TextInput::make('publicaciones_enlace')->label('Enlace de publicaciones')->maxLength(80),
            TextInput::make('aliados_titulo')->label('Título de aliados')->required()->maxLength(180)->columnSpanFull(),
            TextInput::make('aliados_bajada')->label('Bajada de aliados')->maxLength(255)->columnSpanFull(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMicrositioTextosInicio::route('/'),
        ];
    }
}
