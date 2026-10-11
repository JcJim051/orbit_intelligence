<?php

namespace App\Filament\Resources\CapaResource\Pages;

use App\Filament\Resources\CapaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCapas extends ListRecords
{
    protected static string $resource = CapaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva capa'),
        ];
    }
}
