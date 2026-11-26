<?php

namespace App\Filament\Resources\IndicadorPublicos\Pages;

use App\Filament\Resources\IndicadorPublicos\IndicadorPublicoResource;
use App\Services\Micrositio\HojaMicrositio;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ManageIndicadorPublicos extends ManageRecords
{
    protected static string $resource = IndicadorPublicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Crear indicador'),
            Action::make('plantilla')
                ->label('Descargar plantilla')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->url(route('micrositio.excel.plantilla', ['seccion' => 'indicadores'])),
            Action::make('exportar')
                ->label('Exportar registros')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('micrositio.excel.exportar', ['seccion' => 'indicadores'])),
            Action::make('importar')
                ->label('Carga masiva')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->form([
                    FileUpload::make('archivo')
                        ->label('Plantilla diligenciada')
                        ->required()
                        ->disk('local')
                        ->directory('micrositio/importaciones')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data): void {
                    $ruta = Storage::disk('local')->path((string) $data['archivo']);

                    try {
                        $total = app(HojaMicrositio::class)->importar('indicadores', $ruta);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('No se importó la plantilla')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Carga masiva completada')
                        ->body("Se cargaron {$total} indicadores. La visibilidad existente fue conservada.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
