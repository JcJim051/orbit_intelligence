<?php

namespace App\Filament\Resources\EstudioSuelosResource\Pages;

use App\Filament\Resources\EstudioSuelosResource;
use App\Services\Suelos\FlujoValidacionEstudio;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEstudioSuelos extends ViewRecord
{
    protected static string $resource = EstudioSuelosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Editar'),
            Action::make('enviar')->label('Enviar a revisión')
                ->visible(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false)
                ->action(function (): void {
                    app(FlujoValidacionEstudio::class)->enviarARevision($this->getRecord(), auth()->user());
                    Notification::make()->title('Estudio enviado a revisión')->success()->send();
                }),
            Action::make('validar')->label('Validar')
                ->visible(fn (): bool => auth()->user()?->can('validar', $this->getRecord()) ?? false)
                ->authorize(fn (): bool => auth()->user()?->can('validar', $this->getRecord()) ?? false)
                ->requiresConfirmation()
                ->action(function (): void {
                    app(FlujoValidacionEstudio::class)->validar($this->getRecord(), auth()->user(), null);
                    Notification::make()->title('Estudio validado')->success()->send();
                }),
            Action::make('rechazar')->label('Rechazar')
                ->color('danger')
                ->visible(fn (): bool => auth()->user()?->can('validar', $this->getRecord()) ?? false)
                ->authorize(fn (): bool => auth()->user()?->can('validar', $this->getRecord()) ?? false)
                ->schema([
                    Textarea::make('comentario')->label('Comentario')->required(),
                ])
                ->action(function (array $data): void {
                    app(FlujoValidacionEstudio::class)->rechazar($this->getRecord(), auth()->user(), $data['comentario']);
                    Notification::make()->title('Estudio rechazado')->success()->send();
                }),
        ];
    }
}
