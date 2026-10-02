<?php

namespace App\Filament\Resources;

use App\Enums\GeoViewerStatus;
use App\Enums\UserRole;
use App\Filament\Clusters\Geography\GeographyCluster;
use App\Filament\Clusters\Geography\Pages\GeoViewerPreview;
use App\Filament\Clusters\Geography\Pages\ManageGeoViewer;
use App\Filament\Resources\GeoViewerResource\Pages\ListGeoViewers;
use App\Models\GeoViewer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GeoViewerResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = GeoViewer::class;

    protected static ?string $cluster = GeographyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Geovisores';

    protected static ?string $modelLabel = 'geovisor';

    protected static ?string $pluralModelLabel = 'Geovisores';

    protected static ?string $slug = 'geovisores';

    protected static ?int $navigationSort = 30;

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when($user?->role === UserRole::SiidManager, fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('collaborators', fn (Builder $query): Builder => $query->whereKey($user->id));
            }))
            ->withCount('layers');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Geovisor')->searchable()->sortable()->description(fn (GeoViewer $record): string => '/visores/'.$record->slug.'/embed'),
                TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn (GeoViewerStatus $state): string => match ($state) {
                    GeoViewerStatus::Draft => 'Borrador',
                    GeoViewerStatus::Review => 'En revisión',
                    GeoViewerStatus::Published => 'Publicado',
                    GeoViewerStatus::Archived => 'Archivado',
                })->color(fn (GeoViewerStatus $state): string => match ($state) {
                    GeoViewerStatus::Published => 'success',
                    GeoViewerStatus::Review => 'warning',
                    GeoViewerStatus::Archived => 'gray',
                    default => 'info',
                }),
                TextColumn::make('layers_count')->label('Capas')->alignCenter(),
                TextColumn::make('owner.name')->label('Responsable')->placeholder('Sin asignar')->toggleable(),
                TextColumn::make('published_at')->label('Publicado')->dateTime('d/m/Y H:i')->placeholder('—')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options([
                    GeoViewerStatus::Draft->value => 'Borrador',
                    GeoViewerStatus::Review->value => 'En revisión',
                    GeoViewerStatus::Published->value => 'Publicado',
                    GeoViewerStatus::Archived->value => 'Archivado',
                ]),
            ])
            ->recordActions([
                Action::make('preview')->label('Previsualizar')->icon(Heroicon::OutlinedEye)->url(fn (GeoViewer $record): string => GeoViewerPreview::getUrl(['geoViewer' => $record])),
                Action::make('manage')->label('Configurar')->icon(Heroicon::OutlinedCog6Tooth)->url(fn (GeoViewer $record): string => ManageGeoViewer::getUrl(['geoViewer' => $record])),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('No hay geovisores configurados');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeoViewers::route('/'),
        ];
    }
}
