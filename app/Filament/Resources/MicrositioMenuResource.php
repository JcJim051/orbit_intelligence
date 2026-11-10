<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioMenuResource\Pages\ListMicrositioMenus;
use App\Models\MicrositioMenu;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class MicrositioMenuResource extends MicrositioResource
{
    protected static ?string $model = MicrositioMenu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $navigationLabel = 'Menú';

    protected static ?string $modelLabel = 'enlace del menú';

    protected static ?string $pluralModelLabel = 'Menú';

    protected static ?string $slug = 'micrositio-menu';

    protected static ?int $navigationSort = 70;

    public static function seccion(): string
    {
        return 'menu';
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('texto')->label('Texto')->searchable(),
            TextColumn::make('url')->label('URL')->limit(60),
            TextColumn::make('orden')->label('Orden'),
        ];
    }

    public static function campos(): array
    {
        return [
            TextInput::make('texto')->label('Texto')->required()->maxLength(120),
            TextInput::make('url')->label('URL')->required()->maxLength(500),
            TextInput::make('orden')->label('Orden')->numeric()->default(0)->minValue(0),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMicrositioMenus::route('/'),
        ];
    }
}
