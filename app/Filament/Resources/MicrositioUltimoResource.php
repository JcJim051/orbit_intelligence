<?php

namespace App\Filament\Resources;

use App\Contracts\Micrositio\FuenteDatosMapaInicio;
use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioUltimoResource\Pages\ListMicrositioUltimos;
use App\Models\MicrositioUltimo;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class MicrositioUltimoResource extends MicrositioResource
{
    protected static ?string $model = MicrositioUltimo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Lo último de los indicadores';

    protected static ?string $modelLabel = 'tarjeta';

    protected static ?string $pluralModelLabel = 'Lo último de los indicadores';

    protected static ?string $slug = 'micrositio-ultimos';

    protected static ?int $navigationSort = 35;

    public static function seccion(): string
    {
        return 'ultimos';
    }

    public static function tieneExcel(): bool
    {
        return false;
    }

    public static function puedeCrear(): bool
    {
        return MicrositioUltimo::query()->count() < 4;
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('codigo')->label('Código')->searchable(),
            TextColumn::make('nombre')->label('Nombre')->limit(50),
            TextColumn::make('capa_codigo')->label('Capa'),
            TextColumn::make('orden')->label('Orden'),
        ];
    }

    public static function campos(): array
    {
        return [
            TextInput::make('codigo')->label('Código')->required()->maxLength(40),
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(180)->columnSpanFull(),
            Textarea::make('descripcion')->label('Descripción')->rows(3)->columnSpanFull(),
            Select::make('capa_codigo')
                ->label('Capa del mapa')
                ->options(fn (): array => static::capas())
                ->native(false)
                ->helperText('Si elige una capa, el valor, el periodo y la variación se calculan del JSON.')
                ->columnSpanFull(),
            TextInput::make('valor')->label('Valor manual')->maxLength(80),
            TextInput::make('unidad')->label('Unidad')->maxLength(40),
            TextInput::make('periodo')->label('Periodo')->maxLength(40),
            TextInput::make('delta_texto')->label('Variación')->maxLength(120),
            Select::make('delta_tipo')
                ->label('Tipo de variación')
                ->options([
                    'sube_bueno' => 'Sube (favorable)',
                    'baja_malo' => 'Baja (desfavorable)',
                    'neutro' => 'Neutra',
                ])
                ->default('neutro')
                ->required()
                ->native(false),
            TextInput::make('fuente')->label('Fuente')->maxLength(255)->columnSpanFull(),
            TextInput::make('url')->label('Enlace')->maxLength(500)->columnSpanFull(),
            TextInput::make('orden')->label('Orden')->numeric()->default(0)->minValue(0),
            Toggle::make('es_ejemplo')->label('Marcar como dato de ejemplo'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMicrositioUltimos::route('/'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function capas(): array
    {
        $opciones = [];

        foreach (app(FuenteDatosMapaInicio::class)->capas() as $capa) {
            $opciones[$capa['codigo']] = $capa['codigo'].' — '.$capa['nombre'].' ('.$capa['periodo'].')';
        }

        return $opciones;
    }
}
