<?php

namespace App\Filament\Resources\AnalisisAreaResource\Pages;

use App\Filament\Resources\AnalisisAreaResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAnalisisAreas extends ListRecords
{
    protected static string $resource = AnalisisAreaResource::class;

    protected static ?string $title = 'Análisis anteriores';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mapa')->label('Abrir mapa')->icon(Heroicon::OutlinedMap)
                ->url(fn (): string => AnalisisAreaResource::getUrl('index')),
        ];
    }
}
