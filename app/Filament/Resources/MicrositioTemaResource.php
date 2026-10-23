<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioTemaResource\Pages\ListMicrositioTemas;
use App\Models\MicrositioTema;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class MicrositioTemaResource extends MicrositioResource
{
    protected static ?string $model = MicrositioTema::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Temas';

    protected static ?string $modelLabel = 'tema';

    protected static ?string $pluralModelLabel = 'Temas';

    protected static ?string $slug = 'micrositio-temas';

    protected static ?int $navigationSort = 40;

    public static function seccion(): string
    {
        return 'temas';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('indicadores')->withCount('indicadores');
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('slug')
                ->label('Tema')
                ->state(fn (MicrositioTema $record): string => $record->etiqueta())
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where(function (Builder $consulta) use ($search): void {
                        $consulta->where('slug', 'like', '%'.$search.'%')->orWhere('nombre', 'like', '%'.$search.'%');
                    });
                }),
            TextColumn::make('indicadores_count')
                ->label('Indicadores')
                ->action(
                    Action::make('detalleIndicadores')
                        ->modalHeading(fn (MicrositioTema $record): string => 'Indicadores de '.$record->etiqueta())
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->disabled(fn (MicrositioTema $record): bool => $record->indicadores_count < 1)
                        ->modalContent(fn (MicrositioTema $record) => view('filament.micrositio.detalle-conteo', [
                            'columnas' => ['Indicador', 'Público', 'Ejemplo'],
                            'filas' => $record->indicadores->map(fn ($indicador): array => [
                                $indicador->etiqueta(),
                                $indicador->es_publico ? 'Sí' : 'No',
                                $indicador->es_ejemplo ? 'Sí' : 'No',
                            ])->all(),
                        ])),
                )
                ->color(fn (MicrositioTema $record): string => $record->indicadores_count > 0 ? 'primary' : 'gray'),
        ];
    }

    public static function campos(): array
    {
        return [
            TextInput::make('slug')->label('Código')->required()->maxLength(80)->alphaDash(),
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(160),
            TextInput::make('orden')->label('Orden')->numeric()->default(0)->minValue(0),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMicrositioTemas::route('/'),
        ];
    }
}
