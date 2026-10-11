<?php

namespace App\Filament\Resources\EstudioSuelosResource\RelationManagers;

use App\Models\Suelos\TipoAdjunto;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class AdjuntosRelationManager extends RelationManager
{
    protected static string $relationship = 'adjuntos';

    protected static ?string $title = 'Adjuntos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tipo_adjunto_id')->label('Tipo')->options(fn (): array => TipoAdjunto::query()->orderBy('codigo')->get()->mapWithKeys(fn (TipoAdjunto $registro): array => [$registro->id => $registro->etiqueta()])->all())->required(),
            FileUpload::make('archivo')->label('PDF')->required()->disk('local')->directory('suelos/adjuntos')->acceptedFileTypes(['application/pdf']),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tipoAdjunto.codigo')->label('Tipo')->formatStateUsing(fn ($record): string => $record->tipoAdjunto?->etiqueta() ?? ''),
                TextColumn::make('nombre_archivo')->label('Archivo'),
                TextColumn::make('tamano_bytes')->label('Tamaño (bytes)'),
            ])
            ->headerActions([
                CreateAction::make()->label('Adjuntar PDF')->mutateFormDataUsing(function (array $data): array {
                    $ruta = is_array($data['archivo']) ? $data['archivo'][0] : $data['archivo'];
                    $absoluta = Storage::disk('local')->path($ruta);
                    $data['nombre_archivo'] = basename($ruta);
                    $data['ruta_storage'] = $ruta;
                    $data['mime_type'] = 'application/pdf';
                    $data['tamano_bytes'] = max(1, (int) filesize($absoluta));
                    $data['sha256'] = hash_file('sha256', $absoluta);
                    $data['cargado_por'] = auth()->id();
                    unset($data['archivo']);

                    return $data;
                }),
            ]);
    }
}
