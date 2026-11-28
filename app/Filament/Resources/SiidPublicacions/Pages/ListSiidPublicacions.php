<?php

namespace App\Filament\Resources\SiidPublicacions\Pages;

use App\Filament\Resources\SiidPublicacions\SiidPublicacionResource;
use App\Models\SiidPublicacion;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class ListSiidPublicacions extends ListRecords
{
    protected static string $resource = SiidPublicacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publicarTodo')
                ->label('Publicar todo')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publicar todos los indicadores')
                ->modalDescription('Se harán visibles todos los indicadores activos. Los inactivos o no disponibles se conservarán ocultos.')
                ->authorize(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->action(function (): void {
                    [$publicados, $omitidos] = DB::transaction(function (): array {
                        $publicaciones = SiidPublicacion::query()->with('publicable')->get();
                        $publicables = $publicaciones->filter->publicableIsActive();

                        $publicables->each(function (SiidPublicacion $publicacion): void {
                            $publicacion->update([
                                'visible' => true,
                                'publicado_por' => auth()->id(),
                                'publicado_at' => now(),
                            ]);
                        });

                        return [$publicables->count(), $publicaciones->count() - $publicables->count()];
                    });

                    Notification::make()
                        ->title($publicados.' indicadores publicados.')
                        ->body($omitidos > 0 ? $omitidos.' indicadores inactivos o no disponibles permanecieron ocultos.' : null)
                        ->success()
                        ->send();
                }),
            Action::make('despublicarTodo')
                ->label('Despublicar todo')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Despublicar todos los indicadores')
                ->modalDescription('Todos los indicadores dejarán de estar visibles en el portal público.')
                ->authorize(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->action(function (): void {
                    $total = DB::transaction(function (): int {
                        $publicaciones = SiidPublicacion::query()->where('visible', true)->get();
                        $publicaciones->each(fn (SiidPublicacion $publicacion) => $publicacion->update([
                            'visible' => false,
                            'publicado_por' => null,
                            'publicado_at' => null,
                        ]));

                        return $publicaciones->count();
                    });

                    Notification::make()
                        ->title($total.' indicadores despublicados.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
