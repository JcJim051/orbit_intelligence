<?php

namespace App\Filament\Micrositio;

use App\Services\Micrositio\HojaMicrositio;
use App\Services\Micrositio\PublicadorMicrositio;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use UnitEnum;

abstract class MicrositioResource extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = 'Micrositio GIEE';

    abstract public static function seccion(): string;

    /**
     * @return list<TextColumn>
     */
    abstract public static function columnas(): array;

    /**
     * @return list<mixed>
     */
    abstract public static function campos(): array;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::campos());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([...static::columnas(), static::columnaEstado()])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Eliminar'),
            ])
            ->defaultSort(static::columnaOrden())
            ->emptyStateHeading('Todavía no hay registros en el borrador');
    }

    public static function columnaEstado(): TextColumn
    {
        return TextColumn::make('estado_publicacion')
            ->label('Estado')
            ->badge()
            ->state(fn (Model $record): string => app(PublicadorMicrositio::class)->estadoDe(static::seccion(), $record))
            ->formatStateUsing(fn (string $state): string => $state === 'publicado' ? 'Publicado' : 'Borrador')
            ->color(fn (string $state): string => $state === 'publicado' ? 'success' : 'warning');
    }

    /**
     * @return list<Action|CreateAction>
     */
    public static function accionesDePagina(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo')
                ->visible(fn (): bool => static::puedeCrear()),
            Action::make('vistaPrevia')
                ->label('Vista previa')
                ->icon(Heroicon::OutlinedEye)
                ->url(fn (): string => static::urlVistaPrevia())
                ->openUrlInNewTab(),
            Action::make('publicar')
                ->label('Publicar')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publicar esta sección')
                ->modalDescription('El sitio público pasará a mostrar el borrador actual de esta sección.')
                ->authorize(fn (): bool => auth()->user()?->can('publish', static::getModel()) ?? false)
                ->action(function (): void {
                    app(PublicadorMicrositio::class)->publicar(static::seccion());
                    Notification::make()->title('Sección publicada en el micrositio.')->success()->send();
                }),
            Action::make('exportar')
                ->label('Exportar')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (): string => route('micrositio.excel.exportar', ['seccion' => static::seccion()]))
                ->visible(fn (): bool => static::tieneExcel()),
            Action::make('plantilla')
                ->label('Plantilla')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->url(fn (): string => route('micrositio.excel.plantilla', ['seccion' => static::seccion()]))
                ->visible(fn (): bool => static::tieneExcel()),
            Action::make('importar')
                ->label('Importar')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn (): bool => static::tieneExcel())
                ->authorize(fn (): bool => auth()->user()?->can('import', static::getModel()) ?? false)
                ->form([
                    FileUpload::make('archivo')
                        ->label('Archivo Excel')
                        ->required()
                        ->disk('local')
                        ->directory('micrositio/importaciones')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data): void {
                    $ruta = Storage::disk('local')->path((string) $data['archivo']);

                    try {
                        app(HojaMicrositio::class)->importar(static::seccion(), $ruta);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('No se importó el archivo')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()->title('Borrador actualizado desde Excel.')->success()->send();
                }),
        ];
    }

    public static function puedeCrear(): bool
    {
        return true;
    }

    public static function tieneExcel(): bool
    {
        return true;
    }

    public static function columnaOrden(): string
    {
        return 'orden';
    }

    public static function urlVistaPrevia(): string
    {
        return route('micrositio.inicio', ['preview' => 1]);
    }
}
