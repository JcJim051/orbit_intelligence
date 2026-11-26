<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioPieResource\Pages\ListMicrositioPies;
use App\Models\MicrositioPie;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class MicrositioPieResource extends MicrositioResource
{
    protected static ?string $model = MicrositioPie::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Pie de página y contacto';

    protected static ?string $modelLabel = 'pie de página';

    protected static ?string $pluralModelLabel = 'Pie de página y contacto';

    protected static ?string $slug = 'micrositio-pie';

    protected static ?int $navigationSort = 80;

    public static function seccion(): string
    {
        return 'pie';
    }

    public static function tieneExcel(): bool
    {
        return false;
    }

    public static function puedeCrear(): bool
    {
        return MicrositioPie::query()->doesntExist();
    }

    public static function columnaOrden(): string
    {
        return 'id';
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('direccion')->label('Dirección')->limit(60),
            TextColumn::make('correo')->label('Correo'),
        ];
    }

    public static function campos(): array
    {
        return [
            TextInput::make('direccion')->label('Dirección')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('horario')->label('Horario')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('conmutador')->label('Conmutador')->required()->maxLength(80),
            TextInput::make('linea_gratuita')->label('Línea gratuita')->required()->maxLength(80),
            TextInput::make('anticorrupcion')->label('Línea anticorrupción')->required()->maxLength(80),
            TextInput::make('correo')->label('Correo')->email()->required()->maxLength(180),
            TextInput::make('judiciales')->label('Notificaciones judiciales')->email()->required()->maxLength(180),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMicrositioPies::route('/'),
        ];
    }
}
