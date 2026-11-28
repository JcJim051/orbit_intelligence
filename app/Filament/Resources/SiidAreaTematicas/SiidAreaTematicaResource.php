<?php

namespace App\Filament\Resources\SiidAreaTematicas;

use App\Filament\Resources\SiidAreaTematicas\Pages\CreateSiidAreaTematica;
use App\Filament\Resources\SiidAreaTematicas\Pages\EditSiidAreaTematica;
use App\Filament\Resources\SiidAreaTematicas\Pages\ListSiidAreaTematicas;
use App\Filament\Resources\SiidAreaTematicas\Schemas\SiidAreaTematicaForm;
use App\Filament\Resources\SiidAreaTematicas\Tables\SiidAreaTematicasTable;
use App\Models\SiidAreaTematica;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SiidAreaTematicaResource extends Resource
{
    protected static ?string $model = SiidAreaTematica::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Áreas temáticas SIID';

    protected static ?string $modelLabel = 'área temática';

    protected static ?string $slug = 'areas-tematicas-siid';

    protected static ?int $navigationSort = 20;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canSynchronizeSiidCatalog() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return SiidAreaTematicaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiidAreaTematicasTable::configure($table);
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
            'index' => ListSiidAreaTematicas::route('/'),
            'create' => CreateSiidAreaTematica::route('/create'),
            'edit' => EditSiidAreaTematica::route('/{record}/edit'),
        ];
    }
}
