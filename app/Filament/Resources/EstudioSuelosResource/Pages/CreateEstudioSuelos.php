<?php

namespace App\Filament\Resources\EstudioSuelosResource\Pages;

use App\Filament\Resources\EstudioSuelosResource;
use App\Models\Suelos\EstadoValidacion;
use Filament\Resources\Pages\CreateRecord;

class CreateEstudioSuelos extends CreateRecord
{
    protected static string $resource = EstudioSuelosResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['cargado_por'] = auth()->id();
        $data['estado_validacion_id'] = EstadoValidacion::query()->where('codigo', 'CARGADO')->firstOrFail()->id;

        return $data;
    }
}
