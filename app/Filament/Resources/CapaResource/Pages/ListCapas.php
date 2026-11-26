<?php

namespace App\Filament\Resources\CapaResource\Pages;

use App\Filament\Resources\CapaResource;
use App\Models\InteligenciaGeografica\Capa;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCapas extends ListRecords
{
    protected static string $resource = CapaResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return Capa::AVISO_CONECTOR;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva capa'),
        ];
    }
}
