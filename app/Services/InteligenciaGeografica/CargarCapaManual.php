<?php

namespace App\Services\InteligenciaGeografica;

use App\Enums\EstadoFrescuraCapa;
use App\Enums\EstadoSincronizacionCapa;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
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

            $conteos = $this->almacen->fusionar($capa->id);
            $cambio = ($conteos['agregadas'] + $conteos['actualizadas'] + $conteos['eliminadas']) > 0;
            $ahora = now();

            $sincronizacion = CapaSincronizacion::query()->create([
                'capa_id' => $capa->id,
                'iniciado_at' => $ahora,
                'finalizado_at' => $ahora,
                'fecha_corte_detectada' => $fecha,
                'cambio' => $cambio,
                'entidades_agregadas' => $conteos['agregadas'],
                'entidades_actualizadas' => $conteos['actualizadas'],
                'entidades_eliminadas' => $conteos['eliminadas'],
                'estado' => $cambio ? EstadoSincronizacionCapa::Exitoso : EstadoSincronizacionCapa::SinCambios,
                'url_consultada' => 'cargue manual',
                'licencia_detectada' => $licenciaTexto,
                'checksum' => $this->almacen->checksum(),
                'nota' => 'Cargue manual. La fuente pública no está disponible.',
            ]);

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
