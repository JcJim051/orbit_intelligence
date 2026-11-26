<?php

namespace App\Filament\Resources\SiidPublicacions;

use App\Filament\Resources\SiidPublicacions\Pages\EditSiidPublicacion;
use App\Filament\Resources\SiidPublicacions\Pages\ListSiidPublicacions;
use App\Filament\Resources\SiidPublicacions\Schemas\SiidPublicacionForm;
use App\Filament\Resources\SiidPublicacions\Tables\SiidPublicacionsTable;
use App\Models\IndicadorTerritorial;
use App\Models\SiidIndicador;
use App\Models\SiidPublicacion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use UnitEnum;

class SiidPublicacionResource extends Resource
{
    protected static ?string $model = SiidPublicacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Publicación de indicadores';

    protected static ?string $modelLabel = 'publicación';

    protected static ?string $pluralModelLabel = 'Publicación de indicadores';

    protected static ?string $slug = 'publicacion-indicadores';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return SiidPublicacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiidPublicacionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'publicable' => function (MorphTo $morphTo): void {
                $morphTo->morphWith([
                    SiidIndicador::class => ['eje', 'area'],
                    IndicadorTerritorial::class => ['dependencia'],
                ])->morphWithCount([
                    IndicadorTerritorial::class => ['datosAprobados'],
                ]);
            },
        ]);
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
            'index' => ListSiidPublicacions::route('/'),
            'edit' => EditSiidPublicacion::route('/{record}/edit'),
        ];
    }
}
