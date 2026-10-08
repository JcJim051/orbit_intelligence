<?php

namespace App\Services\Intelligence\ReporteSectorial;

use App\Enums\EstadoRevisionPasiva;
use App\Exceptions\CorteCerradoException;
use App\Models\FuenteFinanciacion;
use App\Models\PasivaCarga;
use App\Models\PasivaLinea;
use App\Models\Proyecto;
use App\Models\Seguimiento;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Intelligence\ResolverDependenciaPasiva;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Carga una pasiva del PCT en un seguimiento:
 * 1. Guarda el archivo en el disco privado con su SHA-256.
 * 2. Toma las filas hoja (las que traen " - fuente"), hereda el BPIN de la fila de proyecto de inversión
 *    (rubro 2.3 de seis segmentos) y resuelve dependencia (reglas de pasiva), fuente (catálogo) y proyecto (BPIN).
 * 3. Las filas que no se pueden resolver quedan "pendientes de revisión" con su motivo; no se descartan.
 * 4. Marca la carga como vigente y recalcula los techos.
 */
class ImportadorPasiva
{
    private const PATRON_IDENTIFICACION = '/^\s*(?<unidad>\d{2,8})\s*-\s*(?<rubro>[\d.]+?)\s*(?:-\s*(?<fuente>[0-9A-Za-z]{1,6}))?\s*$/u';

    private const BPIN = '\d{4}(?:[MN]\d{9}|\d{9,11})';

    public function __construct(
        private readonly LectorPasiva $lector,
        private readonly CalculadoraTechos $calculadora,
        private readonly AuditLogger $auditoria,
    ) {}

    public function importar(Seguimiento $seguimiento, UploadedFile $archivo, User $usuario): PasivaCarga
    {
        if ($seguimiento->estaCerrado()) {
            throw new CorteCerradoException;
        }

        $extension = strtolower($archivo->getClientOriginalExtension() ?: (string) $archivo->extension());
        $filas = $this->lector->leer((string) $archivo->getRealPath(), $extension);
        $disk = (string) config('reporte_sectorial.disk');
        $sha256 = hash_file('sha256', (string) $archivo->getRealPath());
        $path = $archivo->store('reporte-sectorial/pasivas/'.$seguimiento->id, $disk);

        try {
            return DB::transaction(function () use ($seguimiento, $archivo, $usuario, $filas, $disk, $sha256, $path): PasivaCarga {
                PasivaCarga::query()->where('seguimiento_id', $seguimiento->id)->where('es_vigente', true)->update(['es_vigente' => false]);
                PasivaLinea::sinFiltroSectorial()->where('seguimiento_id', $seguimiento->id)->whereNotNull('techo_id')->update(['techo_id' => null]);

                $carga = PasivaCarga::create([
                    'seguimiento_id' => $seguimiento->id,
                    'disk' => $disk,
                    'path' => $path,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime' => $archivo->getClientMimeType(),
                    'bytes' => (int) $archivo->getSize(),
                    'sha256' => $sha256,
                    'base_techo' => $this->calculadora->base(),
                    'es_vigente' => true,
                    'uploaded_by' => $usuario->id,
                ]);

                $resumen = $this->guardarLineas($seguimiento, $carga, $filas);
                $carga->update($resumen);

                $this->calculadora->recalcular($seguimiento, $carga, $usuario);

                $this->auditoria->log(null, 'reporte_sectorial.pasiva_cargada', $usuario, [
                    'seguimiento_id' => $seguimiento->id,
                    'pasiva_carga_id' => $carga->id,
                    'sha256' => $sha256,
                ] + $resumen);

                return $carga->fresh();
            });
        } catch (\Throwable $error) {
            Storage::disk($disk)->delete($path);

            throw $error;
        }
    }

    /**
     * @param  list<array{fila: int, valores: array<string, mixed>}>  $filas
     * @return array{lineas_total: int, lineas_inversion: int, lineas_pendientes: int}
     */
    private function guardarLineas(Seguimiento $seguimiento, PasivaCarga $carga, array $filas): array
    {
        $resolver = new ResolverDependenciaPasiva;
        $fuentes = FuenteFinanciacion::query()->pluck('id', 'codigo')->mapWithKeys(fn (int $id, string $codigo): array => [mb_strtoupper($codigo) => $id]);
        $proyectos = [];
        $proyectoActual = null;
        $lineas = [];
        $ahora = now();

        foreach ($filas as $fila) {
            $identificacion = trim((string) $fila['valores']['identificacion']);
            $concepto = trim((string) ($fila['valores']['concepto'] ?? ''));

            if (preg_match(self::PATRON_IDENTIFICACION, $identificacion, $partes) !== 1) {
                continue;
            }

            $rubro = $partes['rubro'];
            $codigoFuente = isset($partes['fuente']) && $partes['fuente'] !== '' ? mb_strtoupper($partes['fuente']) : null;
            $segmentos = count(explode('.', rtrim($rubro, '.')));
            $esInversion = str_starts_with($rubro, '2.3');

            if (! $esInversion || $segmentos < 6) {
                $proyectoActual = null;
            }

            if ($esInversion && $segmentos === 6 && $codigoFuente === null) {
                $proyectoActual = [
                    'bpin' => $this->bpin($concepto),
                    'nombre' => $this->nombreProyecto($concepto),
                    'concepto' => $concepto,
                ];
            }

            if ($codigoFuente === null) {
                continue;
            }

            $motivos = [];
            $bpin = $esInversion ? ($proyectoActual['bpin'] ?? null) : null;
            $textoRegla = trim(($proyectoActual['concepto'] ?? '').' '.$concepto);
            $dependenciaId = $resolver->resolver($identificacion, $textoRegla, $seguimiento->vigencia);
            $fuenteId = $fuentes[$codigoFuente] ?? null;
            $proyectoId = null;

            if ($esInversion) {
                if ($bpin === null) {
                    $motivos[] = 'sin_bpin';
                } else {
                    $proyectoId = $proyectos[$bpin] ??= $this->proyecto($bpin, $proyectoActual['nombre'] ?? $concepto);
                }

                if ($dependenciaId === null) {
                    $motivos[] = 'sin_dependencia';
                }

                if ($fuenteId === null) {
                    $motivos[] = 'fuente_no_catalogada';
                }
            }

            $estado = ! $esInversion
                ? EstadoRevisionPasiva::NoAplica
                : ($motivos === [] ? EstadoRevisionPasiva::Asignada : EstadoRevisionPasiva::Pendiente);

            $lineas[] = [
                'pasiva_carga_id' => $carga->id,
                'seguimiento_id' => $seguimiento->id,
                'fila' => $fila['fila'],
                'identificacion_presupuestal' => $identificacion,
                'unidad_pct' => $partes['unidad'],
                'rubro' => $rubro,
                'codigo_fuente' => $codigoFuente,
                'concepto' => $concepto,
                'bpin' => $bpin,
                'nombre_proyecto' => $esInversion ? ($proyectoActual['nombre'] ?? null) : null,
                'es_inversion' => $esInversion,
                'fuente_financiacion_id' => $fuenteId,
                'dependencia_id' => $dependenciaId,
                'proyecto_id' => $proyectoId,
                'techo_id' => null,
                'apropiacion_inicial' => LectorPasiva::numero($fila['valores']['apropiacion_inicial'] ?? null),
                'modificaciones' => $this->modificaciones($fila['valores']),
                'apropiacion_definitiva' => LectorPasiva::numero($fila['valores']['apropiacion_definitiva'] ?? null),
                'cdp' => LectorPasiva::numero($fila['valores']['cdp'] ?? null),
                'compromisos' => LectorPasiva::numero($fila['valores']['compromisos'] ?? null),
                'obligaciones' => LectorPasiva::numero($fila['valores']['obligaciones'] ?? null),
                'pagos' => LectorPasiva::numero($fila['valores']['pagos'] ?? null),
                'estado_revision' => $estado->value,
                'motivos_revision' => $motivos === [] ? null : json_encode($motivos),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($lineas, 500) as $lote) {
            PasivaLinea::query()->insert($lote);
        }

        return [
            'lineas_total' => count($lineas),
            'lineas_inversion' => count(array_filter($lineas, fn (array $linea): bool => $linea['es_inversion'])),
            'lineas_pendientes' => count(array_filter($lineas, fn (array $linea): bool => $linea['estado_revision'] === EstadoRevisionPasiva::Pendiente->value)),
        ];
    }

    /**
     * El libro plano trae MODIFICACIONES. El libro por periodo la parte en contracréditos,
     * créditos, reducciones y adiciones; el neto usa el Acumulado de cada una.
     *
     * @param  array<string, mixed>  $valores
     */
    private function modificaciones(array $valores): float
    {
        if (array_key_exists('modificaciones', $valores)) {
            return LectorPasiva::numero($valores['modificaciones']);
        }

        return round(
            LectorPasiva::numero($valores['creditos'] ?? null)
            + LectorPasiva::numero($valores['adiciones'] ?? null)
            - LectorPasiva::numero($valores['contracreditos'] ?? null)
            - LectorPasiva::numero($valores['reducciones'] ?? null),
            2,
        );
    }

    private function bpin(string $concepto): ?string
    {
        $patron = '/^\W*(?:VF\W*)?B?BPIN\W*('.self::BPIN.')(?!\d)|^\W*('.self::BPIN.')(?!\d)/iu';

        if (preg_match($patron, $concepto, $coincidencia) !== 1) {
            return null;
        }

        return ($coincidencia[1] ?? '') !== '' ? $coincidencia[1] : ($coincidencia[2] ?? null);
    }

    private function nombreProyecto(string $concepto): string
    {
        $nombre = preg_replace('/^\W*(?:VF\W*)?B?BPIN\W*'.self::BPIN.'\W*|^\W*'.self::BPIN.'\W*/iu', '', $concepto) ?? $concepto;

        return trim($nombre) !== '' ? trim($nombre) : $concepto;
    }

    private function proyecto(string $bpin, string $nombre): int
    {
        $proyecto = Proyecto::sinFiltroSectorial()->withTrashed()->firstOrCreate(['bpin' => $bpin], ['nombre' => $nombre, 'activo' => true]);

        return $proyecto->id;
    }
}
