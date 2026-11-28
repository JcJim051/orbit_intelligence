<?php

namespace App\Filament\Resources\EstudioSuelosResource\RelationManagers;

use App\Enums\PotencialExpansivo;
use App\Enums\PotencialLicuacion;
use App\Filament\Resources\CapaResource;
use App\Models\Suelos\PerfilSueloNsr10;
use App\Models\Suelos\TipoCimentacion;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ParametrosRelationManager extends RelationManager
{
    protected static string $relationship = 'parametrosDiseno';

    protected static ?string $title = 'Parámetros de diseño';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('exploracion_id')->label('Exploración')->options(fn (): array => $this->getOwnerRecord()->exploraciones()->orderBy('codigo')->pluck('codigo', 'id')->all()),
            Select::make('perfil_suelo_nsr10_id')->label('Perfil NSR-10')->options(fn (): array => PerfilSueloNsr10::query()->orderBy('codigo')->get()->mapWithKeys(fn (PerfilSueloNsr10 $registro): array => [$registro->id => $registro->etiqueta()])->all()),
            TextInput::make('vs30_m_s')->label('Vs30 (m/s)')->numeric(),
            TextInput::make('capacidad_portante_adm_kpa')->label('Capacidad portante (kPa)')->numeric(),
            TextInput::make('profundidad_desplante_m')->label('Desplante (m)')->numeric(),
            Select::make('tipo_cimentacion_id')->label('Cimentación')->options(fn (): array => TipoCimentacion::query()->orderBy('codigo')->get()->mapWithKeys(fn (TipoCimentacion $registro): array => [$registro->id => $registro->etiqueta()])->all()),
            Select::make('potencial_expansivo')->label('Potencial expansivo')->options(CapaResource::opciones(PotencialExpansivo::class)),
            Select::make('potencial_licuacion')->label('Potencial de licuación')->options(CapaResource::opciones(PotencialLicuacion::class)),
            Textarea::make('recomendaciones')->label('Recomendaciones')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('exploracion.codigo')->label('Exploración')->placeholder('Del estudio'),
                TextColumn::make('perfilSueloNsr10.codigo')->label('Perfil')->formatStateUsing(fn ($record): string => $record->perfilSueloNsr10?->etiqueta() ?? '—'),
                TextColumn::make('capacidad_portante_adm_kpa')->label('q adm (kPa)'),
            ])
            ->headerActions([CreateAction::make()->label('Nuevos parámetros')])
            ->recordActions([EditAction::make()->label('Editar')]);
    }
}
