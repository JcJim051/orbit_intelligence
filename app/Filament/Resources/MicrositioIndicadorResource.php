<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\IndicadorPublicos\IndicadorPublicoResource;
use App\Filament\Resources\MicrositioIndicadorResource\Pages\ListMicrositioIndicadores;
use App\Models\MicrositioIndicador;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MicrositioIndicadorResource extends MicrositioResource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = MicrositioIndicador::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $navigationLabel = 'Indicadores';

    protected static ?string $modelLabel = 'indicador';

    protected static ?string $pluralModelLabel = 'Indicadores del micrositio';

    protected static ?string $slug = 'micrositio-indicadores';

    protected static ?int $navigationSort = 50;

    public static function seccion(): string
    {
        return 'indicadores';
    }

    public static function urlVistaPrevia(): string
    {
        return route('micrositio.indicadores', ['preview' => 1]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['tema', 'dashboard']);
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('codigo')
                ->label('Indicador')
                ->state(fn (MicrositioIndicador $record): string => $record->etiqueta())
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where(function (Builder $consulta) use ($search): void {
                        $consulta->where('codigo', 'like', '%'.$search.'%')->orWhere('nombre', 'like', '%'.$search.'%');
                    });
                }),
            TextColumn::make('tema.nombre')->label('Eje temático'),
            TextColumn::make('dashboard.name')->label('Tablero')->placeholder('Sin tablero')->wrap(),
            ToggleColumn::make('es_publico')
                ->label('Visible en el micrositio')
                ->onColor('success')
                ->offColor('gray'),
        ];
    }

    public static function campos(): array
    {
        return [
            Toggle::make('es_publico')->label('Visible en el micrositio público'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([...static::columnas(), static::columnaEstado()])
            ->defaultSort('orden')
            ->emptyStateHeading('No hay indicadores creados en el catálogo');
    }

    public static function puedeCrear(): bool
    {
        return false;
    }

    public static function tieneExcel(): bool
    {
        return false;
    }

    public static function accionesDePagina(): array
    {
        return [
            Action::make('gestionarCatalogo')
                ->label('Gestionar indicadores')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(IndicadorPublicoResource::getUrl()),
            ...parent::accionesDePagina(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMicrositioIndicadores::route('/'),
        ];
    }
}
