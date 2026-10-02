<?php

namespace App\Filament\Resources;

use App\Enums\DatasetStatus;
use App\Filament\Clusters\Geography\GeographyCluster;
use App\Filament\Pages\Workspace;
use App\Filament\Resources\SpatialDatasetResource\Pages\ListSpatialDatasets;
use App\Models\SpatialDataset;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SpatialDatasetResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = SpatialDataset::class;

    protected static ?string $cluster = GeographyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Catálogo de datos';

    protected static ?string $modelLabel = 'conjunto espacial';

    protected static ?string $pluralModelLabel = 'Catálogo de datos';

    protected static ?string $slug = 'catalogo-datos';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Conjunto')->searchable()->sortable()->description(fn (SpatialDataset $record): string => $record->slug),
                TextColumn::make('sector')->label('Sector')->searchable()->sortable(),
                TextColumn::make('geometry_type')->label('Geometría')->badge(),
                TextColumn::make('storage_srid')->label('SRID')->prefix('EPSG:')->placeholder('Sin definir'),
                TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn (DatasetStatus $state): string => $state->label())->color(fn (DatasetStatus $state): string => match ($state) {
                    DatasetStatus::Active => 'success',
                    DatasetStatus::Archived => 'gray',
                    default => 'warning',
                }),
                TextColumn::make('materialized_at')->label('Preparación técnica')->dateTime('d/m/Y H:i')->placeholder('Pendiente')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(collect(DatasetStatus::cases())->mapWithKeys(fn (DatasetStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('sector')->label('Sector')->options(fn (): array => SpatialDataset::query()->orderBy('sector')->distinct()->pluck('sector', 'sector')->all()),
            ])
            ->recordActions([
                Action::make('manage')->label('Revisar conjunto')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (SpatialDataset $record): string => Workspace::getUrl(['workspace' => 'catalogo-datos']).'#dataset-'.$record->slug),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('El catálogo espacial está vacío');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpatialDatasets::route('/'),
        ];
    }
}
