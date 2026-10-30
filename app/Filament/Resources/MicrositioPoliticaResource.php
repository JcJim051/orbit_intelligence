<?php

namespace App\Filament\Resources;

use App\Filament\Micrositio\MicrositioResource;
use App\Filament\Resources\MicrositioPoliticaResource\Pages\ListMicrositioPoliticas;
use App\Models\MicrositioPolitica;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class MicrositioPoliticaResource extends MicrositioResource
{
    protected static ?string $model = MicrositioPolitica::class;

    protected static ?string $navigationLabel = 'Políticas públicas';

    protected static ?string $modelLabel = 'política pública';

    protected static ?string $pluralModelLabel = 'Políticas públicas';

    protected static ?string $slug = 'micrositio-politicas';

    protected static ?int $navigationSort = 55;

    public static function seccion(): string
    {
        return 'politicas';
    }

    public static function tieneExcel(): bool
    {
        return false;
    }

    public static function urlVistaPrevia(): string
    {
        return route('micrositio.politicas', ['preview' => 1]);
    }

    public static function columnas(): array
    {
        return [
            TextColumn::make('nombre')->label('Nombre')->searchable(),
            TextColumn::make('vigencia')->label('Vigencia'),
        ];
    }

    public static function campos(): array
    {
        return [
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(200)->columnSpanFull(),
            TextInput::make('slug')->label('Identificador en la dirección')->required()->maxLength(200)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true)
                ->helperText('Letras minúsculas y guiones. Ejemplo: primera-infancia.'),
            TextInput::make('orden')->label('Orden')->integer()->minValue(0)->default(0)->required(),
            Textarea::make('descripcion')->label('Descripción')->required()->maxLength(5000)->rows(3)->columnSpanFull(),
            Textarea::make('objetivo')->label('Objetivo')->maxLength(10000)->rows(4)->columnSpanFull(),
            TextInput::make('ordenanza')->label('Ordenanza')->maxLength(255),
            TextInput::make('vigencia')->label('Vigencia')->maxLength(255),
            TextInput::make('responsable')->label('Responsable')->maxLength(255)->columnSpanFull(),
            FileUpload::make('documentos')->label('Documentos de la política')
                ->multiple()->disk('local')->directory('micrositio/politicas')
                ->storeFileNamesIn('nombres_documentos')
                ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                ->maxSize(10240)->maxFiles(20)->reorderable()
                ->helperText('PDF, Word (.docx) o Excel (.xlsx), hasta 10 MB por archivo. Guarda y publica para mostrarlos en la página.')
                ->columnSpanFull(),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => ListMicrositioPoliticas::route('/')];
    }
}
