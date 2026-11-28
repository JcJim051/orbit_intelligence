<?php

namespace App\Filament\Resources\BankRequestResource\Pages;

use App\Enums\BankRequestStatus;
use App\Filament\Resources\BankRequestResource;
use App\Models\BankRequest;
use App\Services\Bancos\RadicacionBanco;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBankRequest extends ViewRecord
{
    protected static string $resource = BankRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->getRecord()->load(['dependencia', 'files', 'events.user']);
    }

    public function enProcesoAction(): Action
    {
        return $this->accionEstado('enProceso', 'En proceso', BankRequestStatus::EnProceso, 'La secretaría recibe el aviso de que el expediente está en revisión.');
    }

    public function aceptarAction(): Action
    {
        return $this->accionEstado('aceptar', 'Aceptar', BankRequestStatus::Aceptado, 'La secretaría recibe el aviso de que el expediente fue aceptado.');
    }

    public function devolverAction(): Action
    {
        return Action::make('devolver')
            ->label('Devolver')
            ->color('danger')
            ->modalHeading('Devolver a la secretaría')
            ->modalDescription('Escriba por qué se devuelve. La secretaría verá este motivo, corregirá el archivo y lo reenviará con el mismo número de seguimiento.')
            ->modalSubmitActionLabel('Devolver')
            ->visible(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false)
            ->authorize(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false)
            ->schema([
                Textarea::make('observacion')
                    ->label('Por qué se devuelve')
                    ->helperText('Mínimo 10 caracteres. Este texto queda en la trazabilidad.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(2000)
                    ->validationMessages([
                        'required' => 'Escriba por qué se devuelve el expediente.',
                        'min' => 'El motivo debe tener al menos 10 caracteres.',
                        'min.string' => 'El motivo debe tener al menos 10 caracteres.',
                        'max' => 'El motivo no puede pasar de 2000 caracteres.',
                    ])
                    ->rows(5),
            ])
            ->action(function (array $data): void {
                $this->cambiar(BankRequestStatus::Devuelto, $data['observacion']);
            });
    }

    private function accionEstado(string $nombre, string $etiqueta, BankRequestStatus $estado, string $explicacion): Action
    {
        return Action::make($nombre)
            ->label($etiqueta)
            ->modalHeading($etiqueta)
            ->modalDescription($explicacion)
            ->modalSubmitActionLabel('Confirmar')
            ->visible(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false)
            ->authorize(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false)
            ->requiresConfirmation()
            ->action(function () use ($estado): void {
                $this->cambiar($estado, null);
            });
    }

    private function cambiar(BankRequestStatus $estado, ?string $observacion): void
    {
        $solicitud = $this->getRecord();
        if (! $solicitud instanceof BankRequest) {
            return;
        }

        app(RadicacionBanco::class)->cambiarEstado($solicitud, $estado, auth()->user(), $observacion);
        $this->getRecord()->refresh()->load(['dependencia', 'files', 'events.user']);
        Notification::make()->title('Estado actualizado')->success()->send();
    }
}
