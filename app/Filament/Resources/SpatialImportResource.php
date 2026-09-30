<?php

namespace App\Filament\Resources;

use App\Enums\SpatialImportStatus;
use App\Filament\Clusters\Geography\GeographyCluster;
use App\Filament\Pages\Workspace;
use App\Filament\Resources\SpatialImportResource\Pages\ListSpatialImports;
use App\Models\SpatialImport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SpatialImportResource extends Resource
{
    protected static ?string $model = SpatialImport::class;

    protected static ?string $cluster = GeographyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Cargas desde QGIS';

    protected static ?string $modelLabel = 'carga QGIS';

    protected static ?string $pluralModelLabel = 'Cargas desde QGIS';

    protected static ?string $slug = 'cargas-qgis';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Importación')->searchable()->sortable()->description(fn (SpatialImport $record): string => $record->sector),
                TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn (SpatialImportStatus $state): string => $state->label())->color(fn (SpatialImportStatus $state): string => match ($state) {
                    SpatialImportStatus::Failed => 'danger',
                    SpatialImportStatus::Approved => 'success',
                    SpatialImportStatus::Pending => 'warning',
                    default => 'info',
                }),
                TextColumn::make('expires_at')->label('Acceso QGIS')->dateTime('d/m/Y H:i')->sortable()->description(fn (SpatialImport $record): string => $record->expires_at->isPast() ? 'Vencido' : 'Vigente'),
                TextColumn::make('contracts_count')->label('Capas incorporadas')->counts('contracts')->alignCenter(),
                TextColumn::make('created_at')->label('Creada')->since()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(collect(SpatialImportStatus::cases())->mapWithKeys(fn (SpatialImportStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                Action::make('manage')->label('Abrir proceso')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (SpatialImport $record): string => Workspace::getUrl(['workspace' => 'cargas-qgis']).'#import-'.$record->id),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay cargas QGIS registradas');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpatialImports::route('/'),
        ];
    }
}
