<?php

namespace App\Services\InteligenciaGeografica;

use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use App\Models\InteligenciaGeografica\CapaVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Archiva la copia anterior y deja vigente la instantánea normalizada.
 */
class PublicarVersionCapa
{
    public const UMBRAL_CAMBIO = 0.2;

    public function __construct(private AlmacenCapa $almacen) {}

    public function publicar(
        Capa $capa,
        CapaSincronizacion $sincronizacion,
        ?Carbon $fechaCorte,
        string $cita,
        string $url,
        string $licencia,
        string $checksum,
    ): CapaVersion {
        return DB::transaction(function () use ($capa, $sincronizacion, $fechaCorte, $cita, $url, $licencia, $checksum): CapaVersion {
            Capa::query()->whereKey($capa->id)->lockForUpdate()->first();

            $anterior = CapaVersion::query()
                ->where('capa_id', $capa->id)
                ->where('vigente', true)
                ->lockForUpdate()
                ->first();
            $numero = ((int) CapaVersion::query()->where('capa_id', $capa->id)->max('numero')) + 1;
            $conteos = $this->almacen->versionar($capa->id, $numero);

            if ($anterior !== null) {
                $anterior->forceFill(['vigente' => false])->save();
            }

            $version = CapaVersion::query()->create([
                'capa_id' => $capa->id,
                'numero' => $numero,
                'fecha_corte' => $fechaCorte,
                'cita_fuente' => $cita,
                'url_fuente' => $url,
                'licencia' => $licencia,
                'checksum' => $checksum,
                'conteo' => $this->almacen->total(),
                'agregados' => $conteos['agregadas'],
                'modificados' => $conteos['actualizadas'],
                'eliminados' => $conteos['eliminadas'],
                'vigente' => true,
                'sincronizacion_id' => $sincronizacion->id,
            ]);

            $capa->forceFill([
                'alerta' => $this->alerta($anterior, $conteos, $numero),
            ])->save();

            return $version;
        });
    }

    /**
     * @param  array{agregadas: int, actualizadas: int, eliminadas: int}  $conteos
     */
    private function alerta(?CapaVersion $anterior, array $conteos, int $numero): ?string
    {
        if ($anterior === null || $anterior->conteo < 1) {
            return null;
        }

        $cambios = $conteos['agregadas'] + $conteos['actualizadas'] + $conteos['eliminadas'];

        if (($cambios / $anterior->conteo) <= self::UMBRAL_CAMBIO) {
            return null;
        }

        return 'La versión '.$numero.' cambió más del 20% de las entidades ('.$cambios.' de '.$anterior->conteo.').';
    }
}
