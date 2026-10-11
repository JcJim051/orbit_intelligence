<?php

namespace App\Filament\Resources\AnalisisAreaResource\Pages;

use App\Filament\Resources\AnalisisAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnalisisAreas extends ListRecords
{
    protected static string $resource = AnalisisAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo análisis'),
        ];
    }
}
