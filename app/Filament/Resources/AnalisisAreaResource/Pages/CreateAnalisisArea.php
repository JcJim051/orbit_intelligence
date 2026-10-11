<?php

namespace App\Filament\Resources\AnalisisAreaResource\Pages;

use App\Enums\ModoAnalisis;
use App\Filament\Resources\AnalisisAreaResource;
use App\Models\User;
use App\Services\InteligenciaGeografica\CrearAnalisisArea as CrearAnalisis;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateAnalisisArea extends CreateRecord
{
    protected static string $resource = AnalisisAreaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $nombre = null;
        $contenido = null;

        if (! empty($data['archivo'])) {
            $ruta = is_array($data['archivo']) ? $data['archivo'][0] : $data['archivo'];
            $nombre = basename($ruta);
            $contenido = Storage::disk('local')->get($ruta);
        }

        /** @var User $usuario */
        $usuario = auth()->user();

        return app(CrearAnalisis::class)->crear(
            $usuario,
            ModoAnalisis::from($data['modo']),
            $data['investment_project_id'] ?? null,
            $data['geojson'] ?? null,
            $nombre,
            $contenido,
        );
    }
}
