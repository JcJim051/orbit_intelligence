<?php

namespace App\Filament\Resources\SiidIndicadors\Pages;

use App\Filament\Resources\SiidIndicadors\SiidIndicadorResource;
use App\Filament\Resources\SiidSincronizacions\SiidSincronizacionResource;
use App\Services\Siid\SincronizadorListadoSiid;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ListSiidIndicadors extends ListRecords
{
    protected static string $resource = SiidIndicadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sincronizar')
                ->label('Sincronizar ahora')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->visible(fn (): bool => auth()->user()?->canSynchronizeSiidCatalog() ?? false)
                ->action(function (SincronizadorListadoSiid $synchronizer): void {
                    try {
                        $sync = $synchronizer->execute(true, 'manual', auth()->id());
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('No fue posible iniciar la sincronización')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }
                    Notification::make()
                        ->title($sync->estado === 'fallido' ? 'La sincronización falló' : 'Catálogo sincronizado')
                        ->body("{$sync->creados} nuevos, {$sync->actualizados} actualizados, {$sync->inactivados} inactivados y {$sync->conflictos} conflictos.")
                        ->status($sync->estado === 'fallido' ? 'danger' : 'success')
                        ->send();
                }),
            Action::make('historial')
                ->label('Ver sincronizaciones')
                ->icon(Heroicon::OutlinedClock)
                ->url(SiidSincronizacionResource::getUrl()),
        ];
    }
}
