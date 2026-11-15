<?php

namespace App\Filament\Resources\GeoViewerPublications;

use App\Enums\GeoViewerStatus;
use App\Filament\Resources\GeoViewerPublications\Pages\EditGeoViewerPublication;
use App\Filament\Resources\GeoViewerPublications\Pages\ListGeoViewerPublications;
use App\Models\GeoViewerPublication;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class GeoViewerPublicationResource extends Resource
{
    protected static ?string $model = GeoViewerPublication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Micrositio GIEE';

    protected static ?string $navigationLabel = 'Publicación de geovisores';

    protected static ?string $pluralModelLabel = 'Publicación de geovisores';

    protected static ?string $slug = 'publicacion-geovisores';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('visible')->label('Visible en el micrositio')->helperText('Solo puede activarse después de la aprobación técnica de SIG.'),
            Toggle::make('destacado')->label('Destacar en la portada'),
            TextInput::make('orden')->numeric()->minValue(0)->required(),
            TextInput::make('titulo_publico')->label('Título público')->maxLength(255),
            Textarea::make('descripcion_publica')->label('Descripción pública')->rows(4)->columnSpanFull(),
            FileUpload::make('imagen_path')->label('Imagen de portada')->disk('public')->directory('micrositio/geovisores')->image()->maxSize(4096)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('viewer.name')->label('Geovisor')->searchable()->sortable(),
            TextColumn::make('viewer.status')->label('Aprobación SIG')->badge()->formatStateUsing(fn ($state) => $state instanceof GeoViewerStatus ? match ($state) {
                GeoViewerStatus::Published => 'Aprobado', GeoViewerStatus::Review => 'En revisión', GeoViewerStatus::Archived => 'Archivado', default => 'Borrador',
            } : (string) $state),
            TextColumn::make('viewer.layers_count')->label('Capas')->alignCenter(),
            IconColumn::make('visible')->label('Público')->boolean(),
            IconColumn::make('destacado')->label('Destacado')->boolean(),
            TextColumn::make('orden')->sortable(),
        ])->recordActions([EditAction::make()])->defaultSort('orden');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['viewer' => fn ($query) => $query->withCount('layers')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListGeoViewerPublications::route('/'), 'edit' => EditGeoViewerPublication::route('/{record}/edit')];
    }
}
