<?php

namespace App\Filament\Resources\SiidPublicacions\Schemas;

use App\Models\IndicadorTerritorial;
use App\Models\SiidPublicacion;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SiidPublicacionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('visible')->label('Visible en el micrositio')->helperText('Un indicador inactivo nunca se mostrará, aunque esta opción esté activa.'),
                TextInput::make('etiqueta')->label('Etiqueta editorial')->maxLength(255)->helperText('Si se deja vacía se mostrará código — nombre.'),
                Textarea::make('descripcion_publica')->label('Descripción pública')->rows(4)->columnSpanFull()->helperText('Opcional. Si se deja vacía se mostrará la descripción sincronizada desde Google Sheets.'),
                TextInput::make('fuente_publica')->label('Fuente pública')->maxLength(500),
                TextInput::make('url_fuente')
                    ->label('Enlace de la fuente')
                    ->url()
                    ->maxLength(2048)
                    ->placeholder('https://...')
                    ->helperText('Opcional. Si se deja vacío se usará el enlace de la fuente sincronizado desde Google Sheets.'),
                TextInput::make('periodicidad_publica')->label('Periodicidad pública')->maxLength(160)->placeholder('Ejemplo: Anual'),
                TextInput::make('url_serie_datos')
                    ->label('Enlace de la serie de datos')
                    ->url()
                    ->maxLength(2048)
                    ->placeholder('https://...')
                    ->helperText('Opcional. Si se deja vacío se usará el enlace de datos sincronizado desde Google Sheets.'),
                FileUpload::make('imagen_path')
                    ->label('Imagen alusiva del indicador')
                    ->disk('public')
                    ->directory('micrositio/indicadores')
                    ->visibility('public')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->deletable()
                    ->openable()
                    ->downloadable()
                    ->maxSize(4096)
                    ->columnSpanFull()
                    ->helperText('El webmaster puede cargarla o reemplazarla. JPG, PNG o WebP; máximo 4 MB y preferiblemente horizontal.'),
                TextInput::make('orden')->numeric()->minValue(0)->required()->default(0),
                Toggle::make('destacado')->label('Destacado'),
                Toggle::make('mostrar_serie')
                    ->label('Mostrar serie y gráfica territorial')
                    ->helperText('Publica únicamente los datos aprobados. Las observaciones y evidencias permanecen internas.')
                    ->visible(fn (?SiidPublicacion $record): bool => $record?->publicable_type === IndicadorTerritorial::class),
                Select::make('tablero_tipo')->label('Motor del tablero')->options(['looker' => 'Looker Studio', 'superset' => 'Apache Superset'])->native(false),
                TextInput::make('tablero_referencia')
                    ->label('URL embed o UUID')
                    ->columnSpanFull()
                    ->helperText('Opcional. Si se deja vacío se usará el enlace o UUID sincronizado desde Google Sheets.'),
            ]);
    }
}
