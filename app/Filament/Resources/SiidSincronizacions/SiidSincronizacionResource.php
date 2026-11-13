<?php

namespace App\Filament\Resources\SiidSincronizacions;

use App\Filament\Resources\SiidSincronizacions\Pages\ListSiidSincronizacions;
use App\Filament\Resources\SiidSincronizacions\Schemas\SiidSincronizacionForm;
use App\Filament\Resources\SiidSincronizacions\Tables\SiidSincronizacionsTable;
use App\Models\SiidSincronizacion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SiidSincronizacionResource extends Resource
{
    protected static ?string $model = SiidSincronizacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Sincronizaciones SIID';

    protected static ?string $modelLabel = 'sincronización';

    protected static ?string $slug = 'sincronizaciones-siid';

    protected static ?int $navigationSort = 40;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canViewSiidCatalog() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return SiidSincronizacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiidSincronizacionsTable::configure($table);
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
            'index' => ListSiidSincronizacions::route('/'),
        ];
    }
}
