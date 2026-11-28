<?php

namespace App\Filament\Resources\EstudioSuelosResource\Pages;

use App\Filament\Resources\EstudioSuelosResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEstudiosSuelos extends ListRecords
{
    protected static string $resource = EstudioSuelosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo estudio'),
        ];
    }
}
