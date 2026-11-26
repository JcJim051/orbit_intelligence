<?php

namespace App\Filament\Resources\PublicToolPublications;

use App\Filament\Resources\PublicToolPublications\Pages\EditPublicToolPublication;
use App\Filament\Resources\PublicToolPublications\Pages\ListPublicToolPublications;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\PublicSpatialAnalysisSession;
use App\Models\PublicToolPublication;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PublicToolPublicationResource extends Resource
{
    protected static ?string $model = PublicToolPublication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAmericas;

    protected static string|UnitEnum|null $navigationGroup = 'Micrositio GIEE';

    protected static ?string $navigationLabel = 'Publicación análisis geoespacial';

    protected static ?string $pluralModelLabel = 'Publicación análisis geoespacial';

    protected static ?string $slug = 'publicacion-analisis-geoespacial';

    protected static ?int $navigationSort = 61;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('visible')->label('Vista pública activa')->helperText('Al apagarla, la ruta pública y su navegación dejan de estar disponibles.'),
            TextInput::make('titulo')->required()->maxLength(255),
            Textarea::make('descripcion')->rows(5)->columnSpanFull(),
            FileUpload::make('imagen_path')->label('Imagen de portada')->disk('public')->directory('micrositio/herramientas')->image()->maxSize(4096)->columnSpanFull(),
            TextInput::make('orden')->numeric()->minValue(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('titulo')->label('Herramienta'),
            IconColumn::make('visible')->label('Vista pública')->boolean(),
            TextColumn::make('capas')->label('Capas públicas')->state(fn () => Capa::query()->disponibleParaAnalisisPublico()->count()),
            TextColumn::make('cola')->label('Procesamiento')->state(fn () => config('queue.default') === 'sync' ? 'Sincrónico' : 'Worker requerido'),
            TextColumn::make('ultima')->label('Última ejecución')->state(fn () => PublicSpatialAnalysisSession::query()->latest()->value('created_at'))->dateTime('d/m/Y H:i')->placeholder('Sin ejecuciones'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPublicToolPublications::route('/'), 'edit' => EditPublicToolPublication::route('/{record}/edit')];
    }
}
