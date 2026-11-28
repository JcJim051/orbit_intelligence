<?php

namespace App\Filament\Resources\CapaResource\Pages;

use App\Enums\EstadoFrescuraCapa;
use App\Filament\Resources\CapaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCapa extends CreateRecord
{
    protected static string $resource = CapaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['estado_frescura'] = EstadoFrescuraCapa::PosiblementeDesactualizada;

        return $data;
    }
}
