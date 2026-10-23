<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioPublicacionResource\Pages\ListMicrositioPublicaciones;
use App\Models\MicrositioPublicacion;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

class MicrositioPublicacionResource extends MicrositioResource
{
    protected static ?string $model = MicrositioPublicacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Estudios y publicaciones';

    protected static ?string $modelLabel = 'publicación';

    protected static ?string $pluralModelLabel = 'Estudios y publicaciones';

    protected static ?string $slug = 'micrositio-publicaciones';

    protected static ?int $navigationSort = 60;

    public static function seccion(): string
    {
        return 'publicaciones';
    }

    public static function urlVistaPrevia(): string
    {
        return route('micrositio.inicio', ['preview' => 1]).'#publicaciones';
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('tipo')->label('Tipo')->searchable(),
            TextColumn::make('titulo')->label('Título')->searchable()->limit(60),
            TextColumn::make('fecha')->label('Fecha')->date('d/m/Y'),
            IconColumn::make('es_ejemplo')->label('Ejemplo')->boolean(),
        ];
    }

    public static function campos(): array
    {
        return [
            TextInput::make('tipo')->label('Tipo')->required()->maxLength(80),
            DatePicker::make('fecha')->label('Fecha')->required()->native(false)->displayFormat('d/m/Y'),
            TextInput::make('titulo')->label('Título')->required()->maxLength(200)->columnSpanFull(),
            Textarea::make('resumen')->label('Resumen')->required()->rows(3)->columnSpanFull(),
            FileUpload::make('archivo_path')
                ->label('Archivo')
                ->disk('local')
                ->directory('micrositio/publicaciones')
                ->storeFileNamesIn('nombre_archivo')
                ->columnSpanFull(),
            TextInput::make('orden')->label('Orden')->numeric()->default(0)->minValue(0),
            Toggle::make('es_ejemplo')->label('Marcar como dato de ejemplo'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMicrositioPublicaciones::route('/'),
        ];
    }
}
