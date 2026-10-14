<?php

namespace App\Services\InteligenciaGeografica;

use App\Enums\EstadoSincronizacionCapa;
use App\Models\InteligenciaGeografica\Capa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GeoJsonCapa
{
    /**
     * @return array{type: string, features: list<mixed>, pendiente: bool}
     */
    public function porBbox(Capa $capa, string $bbox): array
    {
        [$oeste, $sur, $este, $norte] = $this->bbox($bbox);

        $fila = DB::selectOne(
            "select coalesce(json_agg(feature), '[]'::json) as features
             from (
                select json_build_object(
                    'type', 'Feature',
                    'id', cache.id,
                    'geometry', ST_AsGeoJSON(ST_Transform(cache.geom, 4326))::json,
                    'properties', json_build_object(
                        'identificador', coalesce(cache.identificador_origen, 'sin identificador'),
                        'atributos', cache.atributos,
                        'capa', ?::text
                    )
                ) as feature
                from inteligencia.capa_objeto_cache cache
                where cache.capa_id = ?::bigint
                  and cache.valid_to is null
                  and ST_Intersects(
                    cache.geom,
                    ST_Transform(ST_MakeEnvelope(?::float8, ?::float8, ?::float8, ?::float8, 4326), 9377)
                  )
                order by cache.identificador_origen
                limit 1500
             ) visibles",
            [$capa->etiqueta(), $capa->id, $oeste, $sur, $este, $norte],
        );

        $features = json_decode((string) ($fila->features ?? '[]'), true);
        $tieneCopia = $capa->objetosCache()->exists();
        $tieneSync = $capa->sincronizaciones()->whereIn('estado', EstadoSincronizacionCapa::atribuibles())->exists();

        return [
            'type' => 'FeatureCollection',
            'features' => is_array($features) ? $features : [],
            'pendiente' => ! $tieneCopia && ! $tieneSync,
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function bbox(string $bbox): array
    {
        $partes = array_map('trim', explode(',', $bbox));

        if (count($partes) !== 4 || collect($partes)->contains(fn (string $valor): bool => ! is_numeric($valor))) {
            throw ValidationException::withMessages([
                'bbox' => 'El área visible debe ser oeste,sur,este,norte.',
            ]);
        }

        $oeste = (float) $partes[0];
        $sur = (float) $partes[1];
        $este = (float) $partes[2];
        $norte = (float) $partes[3];

        if ($oeste < -180 || $este > 180 || $sur < -90 || $norte > 90 || $oeste >= $este || $sur >= $norte) {
            throw ValidationException::withMessages([
                'bbox' => 'El área visible no es una extensión geográfica válida.',
            ]);
        }

        if (($este - $oeste) > 12 || ($norte - $sur) > 12) {
            throw ValidationException::withMessages([
                'bbox' => 'Acerque el mapa para cargar las entidades de la capa.',
            ]);
        }

        return [$oeste, $sur, $este, $norte];
    }
}
