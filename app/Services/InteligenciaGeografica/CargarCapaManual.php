<?php

namespace App\Services\InteligenciaGeografica;

use App\Enums\EstadoFrescuraCapa;
use App\Enums\EstadoSincronizacionCapa;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use App\Models\InteligenciaGeografica\CapaVersion;
use App\Services\InteligenciaGeografica\Conectores\CatalogoCapasAbiertas;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Cargue de un administrador cuando la fuente pública no se puede consultar.
 * La cita y la fecha de corte son obligatorias: no se inventan.
 */
class CargarCapaManual
{
    public function __construct(
        private CatalogoCapasAbiertas $catalogo,
        private LectorArchivoCapa $lector,
        private AlmacenCapa $almacen,
        private PublicarVersionCapa $versiones,
    ) {}

    public function cargar(
        Capa $capa,
        string $nombre,
        string $contenido,
        string $cita,
        string $fechaCorte,
        ?string $licencia,
    ): CapaSincronizacion {
        $definicion = $this->catalogo->definicion($capa->codigo);

        if ($definicion === null || $definicion->disponible) {
            throw ValidationException::withMessages([
                'archivo' => 'El cargue manual solo está habilitado cuando la fuente pública no está disponible.',
            ]);
        }

        $cita = trim($cita);

        if ($cita === '') {
            throw ValidationException::withMessages([
                'cita_fuente' => 'Indique la fuente del archivo.',
            ]);
        }

        $fecha = $this->fecha($fechaCorte);
        $licenciaTexto = trim((string) $licencia);
        $licenciaTexto = $licenciaTexto !== '' ? $licenciaTexto : 'No declarada en el cargue.';

        try {
            $entidades = $this->lector->leer($nombre, $contenido);
        } catch (\InvalidArgumentException $excepcion) {
            throw ValidationException::withMessages(['archivo' => $excepcion->getMessage()]);
        }

        $this->almacen->abrir();

        try {
            foreach (collect($entidades)->groupBy('srid') as $srid => $grupo) {
                $this->almacen->agregar($grupo->map(fn (array $entidad): array => [
                    'id' => $entidad['id'],
                    'atributos' => $entidad['atributos'],
                    'geom' => $entidad['geom'],
                ])->values()->all(), (int) $srid);
            }

            if ($this->almacen->total() === 0) {
                throw ValidationException::withMessages([
                    'archivo' => 'Ninguna geometría pudo reproyectarse a EPSG:9377.',
                ]);
            }

            $checksum = $this->almacen->checksum();
            $vigente = CapaVersion::query()->where('capa_id', $capa->id)->where('vigente', true)->first();
            $igual = $vigente !== null && hash_equals($vigente->checksum, $checksum);
            $ahora = now();

            $sincronizacion = CapaSincronizacion::query()->create([
                'capa_id' => $capa->id,
                'iniciado_at' => $ahora,
                'finalizado_at' => $ahora,
                'fecha_corte_detectada' => $fecha,
                'cambio' => false,
                'entidades_agregadas' => 0,
                'entidades_actualizadas' => 0,
                'entidades_eliminadas' => 0,
                'estado' => EstadoSincronizacionCapa::EnProceso,
                'url_consultada' => 'cargue manual',
                'licencia_detectada' => $licenciaTexto,
                'checksum' => $checksum,
                'nota' => 'Cargue manual. La fuente pública no está disponible.',
            ]);

            if ($igual) {
                $sincronizacion->fill([
                    'estado' => EstadoSincronizacionCapa::RepublicadoSinCambios,
                ])->save();
            } else {
                $version = $this->versiones->publicar(
                    $capa,
                    $sincronizacion,
                    $fecha,
                    $cita,
                    'cargue manual',
                    $licenciaTexto,
                    $checksum,
                );
                $cambio = ($version->agregados + $version->modificados + $version->eliminados) > 0;
                $sincronizacion->fill([
                    'cambio' => $cambio,
                    'entidades_agregadas' => $version->agregados,
                    'entidades_actualizadas' => $version->modificados,
                    'entidades_eliminadas' => $version->eliminados,
                    'estado' => $cambio ? EstadoSincronizacionCapa::Exitoso : EstadoSincronizacionCapa::SinCambios,
                ])->save();
            }

            $capa->fill([
                'cita_fuente' => $cita,
                'licencia' => $licenciaTexto,
                'fecha_corte_fuente' => $fecha,
                'fecha_ultima_sincronizacion' => $ahora,
                'estado_frescura' => EstadoFrescuraCapa::Vigente,
            ])->save();

            return $sincronizacion;
        } finally {
            $this->almacen->descartar();
        }
    }

    private function fecha(string $fechaCorte): Carbon
    {
        $fechaCorte = trim($fechaCorte);

        if ($fechaCorte === '') {
            throw ValidationException::withMessages([
                'fecha_corte' => 'Indique la fecha de corte del archivo.',
            ]);
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaCorte) === 1) {
                $fecha = Carbon::createFromFormat('Y-m-d', $fechaCorte, 'America/Bogota');

                if ($fecha === false) {
                    throw ValidationException::withMessages([
                        'fecha_corte' => 'La fecha de corte no es válida.',
                    ]);
                }

                return $fecha->startOfDay()->utc();
            }

            return Carbon::parse($fechaCorte)->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'fecha_corte' => 'La fecha de corte no es válida.',
            ]);
        }
    }
}
