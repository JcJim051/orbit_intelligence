<?php

namespace App\Filament\Resources\SiidEquivalenciaAreas;

use App\Filament\Resources\SiidEquivalenciaAreas\Pages\CreateSiidEquivalenciaArea;
use App\Filament\Resources\SiidEquivalenciaAreas\Pages\EditSiidEquivalenciaArea;
use App\Filament\Resources\SiidEquivalenciaAreas\Pages\ListSiidEquivalenciaAreas;
use App\Filament\Resources\SiidEquivalenciaAreas\Schemas\SiidEquivalenciaAreaForm;
use App\Filament\Resources\SiidEquivalenciaAreas\Tables\SiidEquivalenciaAreasTable;
use App\Models\SiidEquivalenciaArea;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SiidEquivalenciaAreaResource extends Resource
{
    protected static ?string $model = SiidEquivalenciaArea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Equivalencias de áreas';

    protected static ?string $modelLabel = 'equivalencia de área';

    protected static ?string $slug = 'equivalencias-areas-siid';

    protected static ?int $navigationSort = 30;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canSynchronizeSiidCatalog() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return SiidEquivalenciaAreaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiidEquivalenciaAreasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiidEquivalenciaAreas::route('/'),
            'create' => CreateSiidEquivalenciaArea::route('/create'),
            'edit' => EditSiidEquivalenciaArea::route('/{record}/edit'),
        ];
    }
}
