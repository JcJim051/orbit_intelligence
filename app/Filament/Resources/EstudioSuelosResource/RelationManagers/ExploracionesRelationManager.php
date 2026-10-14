<?php

namespace App\Filament\Resources\EstudioSuelosResource\RelationManagers;

use App\Models\Suelos\MetodoCoordenadas;
use App\Models\Suelos\SistemaCoordenadas;
use App\Models\Suelos\TipoExploracion;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExploracionesRelationManager extends RelationManager
{
    protected static string $relationship = 'exploraciones';

    protected static ?string $title = 'Exploraciones';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')->label('Código')->required()->maxLength(30),
            Select::make('tipo_exploracion_id')->label('Tipo')->relationship('tipoExploracion', 'nombre')
                ->getOptionLabelFromRecordUsing(fn (TipoExploracion $record): string => $record->etiqueta())->required()->searchable()->preload(),
            Select::make('sistema_coordenadas_id')->label('Sistema de coordenadas')->relationship('sistemaCoordenadas', 'nombre')
                ->getOptionLabelFromRecordUsing(fn (SistemaCoordenadas $record): string => $record->etiqueta())->required()->searchable()->preload(),
            Select::make('metodo_coordenadas_id')->label('Método')->relationship('metodoCoordenadas', 'nombre')
                ->getOptionLabelFromRecordUsing(fn (MetodoCoordenadas $record): string => $record->etiqueta())->required()->searchable()->preload(),
            TextInput::make('x_original')->label('X / longitud')->numeric()->required(),
            TextInput::make('y_original')->label('Y / latitud')->numeric()->required(),
            TextInput::make('cota_msnm')->label('Cota (m s.n.m.)')->numeric(),
            TextInput::make('profundidad_total_m')->label('Profundidad total (m)')->numeric()->required(),
            Toggle::make('nivel_freatico_encontrado')->label('Nivel freático encontrado')->default(false),
            TextInput::make('nivel_freatico_m')->label('Nivel freático (m)')->numeric(),
            ViewField::make('mapa')->label('Punto en el mapa')->dehydrated(false)
                ->view('filament.suelos.mapa-punto')
                ->viewData(function (ViewField $campo): array {
                    $base = preg_replace('/\.mapa$/', '', (string) $campo->getStatePath()) ?? '';

                    return [
                        'rutaX' => $base.'.x_original',
                        'rutaY' => $base.'.y_original',
                    ];
                })
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Exploración')->formatStateUsing(fn ($record): string => $record->codigo.' — '.($record->tipoExploracion?->nombre ?? '')),
                TextColumn::make('profundidad_total_m')->label('Profundidad (m)'),
                TextColumn::make('nivel_freatico_m')->label('Nivel freático (m)')->placeholder('No encontrado'),
            ])
            ->headerActions([CreateAction::make()->label('Nueva exploración')])
            ->recordActions([EditAction::make()->label('Editar')]);
    }
}
