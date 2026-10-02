<?php

namespace App\Filament\Resources\OpenDataSourceResource\Pages;

use App\Filament\Pages\Workspace;
use App\Filament\Resources\OpenDataSourceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListOpenDataSources extends ListRecords
{
    protected static string $resource = OpenDataSourceResource::class;

    protected function getHeaderActions(): array
    {
        return auth()->user()?->canManageOpenDataSources() ? [
            Action::make('connect')->label('Crear visor desde Datos Abiertos')->icon(Heroicon::OutlinedPlus)->url(Workspace::getUrl(['workspace' => 'fuentes-abiertas'])),
        ] : [];
    }
}
