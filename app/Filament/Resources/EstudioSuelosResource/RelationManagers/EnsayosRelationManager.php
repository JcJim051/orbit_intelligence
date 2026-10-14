<?php

namespace App\Filament\Resources\EstudioSuelosResource\RelationManagers;

use App\Models\Suelos\Ensayo;
use App\Models\Suelos\Exploracion;
use App\Models\Suelos\TipoEnsayo;
use App\Models\Suelos\UnidadMedida;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnsayosRelationManager extends RelationManager
{
    protected static string $relationship = 'ensayos';

    protected static ?string $title = 'Ensayos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('exploracion_id')->label('Exploración')->options(fn (): array => $this->getOwnerRecord()->exploraciones()->orderBy('codigo')->pluck('codigo', 'id')->all())->required(),
            Select::make('tipo_ensayo_id')->label('Tipo de ensayo')->options(fn (): array => TipoEnsayo::query()->orderBy('codigo')->get()->mapWithKeys(fn (TipoEnsayo $registro): array => [$registro->id => $registro->etiqueta()])->all())->required()->searchable(),
            TextInput::make('profundidad_desde_m')->label('Desde (m)')->numeric()->required(),
            TextInput::make('profundidad_hasta_m')->label('Hasta (m)')->numeric(),
            TextInput::make('valor')->label('Valor')->numeric()->required(),
            Select::make('unidad_original_id')->label('Unidad original')->options(fn (): array => UnidadMedida::query()->orderBy('codigo')->get()->mapWithKeys(fn (UnidadMedida $registro): array => [$registro->id => $registro->etiqueta()])->all())->searchable(),
            Toggle::make('es_rechazo')->label('Rechazo')->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('exploracion.codigo')->label('Exploración'),
                TextColumn::make('tipoEnsayo.codigo')->label('Ensayo')->formatStateUsing(fn ($record): string => $record->tipoEnsayo?->etiqueta() ?? ''),
                TextColumn::make('valor')->label('Valor'),
                TextColumn::make('profundidad_desde_m')->label('Desde (m)'),
            ])
            ->headerActions([
                CreateAction::make()->label('Nuevo ensayo')->using(function (array $data): Ensayo {
                    $exploracion = Exploracion::query()->findOrFail($data['exploracion_id']);
                    unset($data['exploracion_id']);

                    return $exploracion->ensayos()->create($data);
                }),
            ]);
    }
}
