<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioHeroResource\Pages\ListMicrositioHeroes;
use App\Models\MicrositioHero;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class MicrositioHeroResource extends MicrositioResource
{
    protected static ?string $model = MicrositioHero::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Hero y banner';

    protected static ?string $modelLabel = 'hero';

    protected static ?string $pluralModelLabel = 'Hero y banner';

    protected static ?string $slug = 'micrositio-hero';

    protected static ?int $navigationSort = 20;

    public static function seccion(): string
    {
        return 'hero';
    }

    public static function tieneExcel(): bool
    {
        return false;
    }

    public static function puedeCrear(): bool
    {
        return MicrositioHero::query()->doesntExist();
    }

    public static function columnaOrden(): string
    {
        return 'id';
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('titulo')->label('Título')->searchable(),
            TextColumn::make('subtitulo')->label('Subtítulo')->limit(80),
        ];
    }

    public static function campos(): array
    {
        return [
            TextInput::make('titulo')->label('Título')->required()->maxLength(180)->columnSpanFull(),
            Textarea::make('subtitulo')->label('Subtítulo')->required()->rows(3)->columnSpanFull(),
            FileUpload::make('banner_path')
                ->label('Banner')
                ->disk('local')
                ->directory('micrositio/banner')
                ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                ->columnSpanFull(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMicrositioHeroes::route('/'),
        ];
    }
}
