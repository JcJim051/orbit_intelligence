<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Clusters\Geography\GeographyCluster;
use App\Filament\Resources\OpenDataSourceResource\Pages\ListOpenDataSources;
use App\Models\OpenDataSource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OpenDataSourceResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = OpenDataSource::class;

    protected static ?string $cluster = GeographyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAmericas;

    protected static ?string $navigationLabel = 'Datos abiertos';

    protected static ?string $modelLabel = 'fuente abierta';

    protected static ?string $pluralModelLabel = 'Datos abiertos';

    protected static ?string $slug = 'datos-abiertos';

    protected static ?int $navigationSort = 40;

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when(
                $user?->role === UserRole::SiidManager,
                fn (Builder $query): Builder => $query->where('owner_id', $user->id),
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Fuente')->searchable()->sortable()->description(fn (OpenDataSource $record): string => 'Datos.gov.co · '.$record->dataset_id),
                TextColumn::make('geography_mode')->label('Geografía')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'dane_municipality' => 'Municipios DANE',
                    'coordinates' => 'Coordenadas',
                    'geometry' => 'Geometría',
                    default => $state,
                }),
                TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'published' => 'Publicada',
                    'review' => 'En revisión',
                    'draft' => 'Borrador',
                    default => $state,
                })->color(fn (string $state): string => match ($state) {
                    'published' => 'success',
                    'review' => 'warning',
                    default => 'info',
                }),
                TextColumn::make('last_success_at')->label('Última sincronización')->since()->placeholder('Sin sincronizar')->sortable(),
                TextColumn::make('last_error')->label('Diagnóstico')->limit(45)->placeholder('Sin novedades')->color(fn (?string $state): string => filled($state) ? 'danger' : 'gray')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(['draft' => 'Borrador', 'review' => 'En revisión', 'published' => 'Publicada']),
                SelectFilter::make('geography_mode')->label('Geografía')->options(['dane_municipality' => 'Municipios DANE', 'coordinates' => 'Coordenadas', 'geometry' => 'Geometría']),
            ])
            ->recordActions([
                Action::make('preview')->label('Previsualizar')->icon(Heroicon::OutlinedEye)->url(fn (OpenDataSource $record): string => route('admin.open-data-sources.preview', $record))->openUrlInNewTab(),
                Action::make('official')->label('Portada oficial')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (OpenDataSource $record): string => $record->landing_page_url)->openUrlInNewTab(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('Todavía no hay fuentes abiertas conectadas');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpenDataSources::route('/'),
        ];
    }
}
