<?php

namespace App\Filament\Resources\SiidIndicadors;

use App\Filament\Resources\SiidIndicadors\Pages\ListSiidIndicadors;
use App\Filament\Resources\SiidIndicadors\Schemas\SiidIndicadorForm;
use App\Filament\Resources\SiidIndicadors\Tables\SiidIndicadorsTable;
use App\Models\SiidIndicador;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SiidIndicadorResource extends Resource
{
    protected static ?string $model = SiidIndicador::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Catálogo SIID';

    protected static ?string $modelLabel = 'indicador SIID';

    protected static ?string $pluralModelLabel = 'Catálogo SIID sincronizado';

    protected static ?string $slug = 'catalogo-siid';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return SiidIndicadorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiidIndicadorsTable::configure($table);
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
            'index' => ListSiidIndicadors::route('/'),
        ];
    }
}
