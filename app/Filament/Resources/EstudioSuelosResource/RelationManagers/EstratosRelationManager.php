<?php

namespace App\Filament\Resources\EstudioSuelosResource\RelationManagers;

use App\Models\Suelos\ClasificacionAashto;
use App\Models\Suelos\ClasificacionUscs;
use App\Models\Suelos\Estrato;
use App\Models\Suelos\Exploracion;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EstratosRelationManager extends RelationManager
{
    protected static string $relationship = 'estratos';

    protected static ?string $title = 'Estratos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('exploracion_id')->label('Exploración')->options(fn (): array => $this->getOwnerRecord()->exploraciones()->orderBy('codigo')->pluck('codigo', 'id')->all())->required(),
            TextInput::make('profundidad_desde_m')->label('Desde (m)')->numeric()->required(),
            TextInput::make('profundidad_hasta_m')->label('Hasta (m)')->numeric()->required(),
            Select::make('clasificacion_uscs_id')->label('USCS')->options(fn (): array => ClasificacionUscs::query()->orderBy('codigo')->get()->mapWithKeys(fn (ClasificacionUscs $registro): array => [$registro->id => $registro->etiqueta()])->all())->searchable(),
            Select::make('clasificacion_aashto_id')->label('AASHTO')->options(fn (): array => ClasificacionAashto::query()->orderBy('codigo')->get()->mapWithKeys(fn (ClasificacionAashto $registro): array => [$registro->id => $registro->etiqueta()])->all())->searchable(),
            Textarea::make('descripcion')->label('Descripción')->required()->columnSpanFull(),
            Toggle::make('es_relleno')->label('Es relleno')->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('exploracion.codigo')->label('Exploración'),
                TextColumn::make('profundidad_desde_m')->label('Desde'),
                TextColumn::make('profundidad_hasta_m')->label('Hasta'),
                TextColumn::make('clasificacionUscs.codigo')->label('USCS')->formatStateUsing(fn ($record): string => $record->clasificacionUscs?->etiqueta() ?? '—'),
                TextColumn::make('descripcion')->label('Descripción')->limit(40),
            ])
            ->headerActions([
                CreateAction::make()->label('Nuevo estrato')->using(function (array $data): Estrato {
                    $exploracion = Exploracion::query()->findOrFail($data['exploracion_id']);
                    unset($data['exploracion_id']);

                    return $exploracion->estratos()->create($data);
                }),
            ]);
    }
}
