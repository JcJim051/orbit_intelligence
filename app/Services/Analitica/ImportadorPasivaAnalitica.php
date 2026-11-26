<?php

namespace App\Services\Analitica;

use App\Models\AnaliticaSincronizacion;
use App\Models\PasivaCarga;
use App\Models\PasivaRubro;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ImportadorPasivaAnalitica
{
    public function __construct(private readonly LectorPasivaHacienda $lector) {}

    /**
     * @param  array{pasiva_carga_id?: int|null, sha256?: string|null, origen?: string|null}  $meta
     */
    public function importar(string $ruta, string $extension, ?string $corte = null, array $meta = []): ResultadoCargaPasiva
    {
        $corte = $corte ?: $this->lector->corteDetectado($ruta, $extension);

        $sha256 = is_file($ruta) ? hash_file('sha256', $ruta) : '';
        $cargaId = $meta['pasiva_carga_id'] ?? null;

        if ($corte === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $corte) !== 1) {
            $this->registrar('error', null, $sha256, 0, $meta['origen'] ?? null, $cargaId, 'Indique el corte de la pasiva con --corte=AAAA-MM-DD.');

            throw new InvalidArgumentException('Indique el corte de la pasiva con --corte=AAAA-MM-DD.');
        }

        if (isset($meta['sha256']) && $meta['sha256'] !== '' && ! hash_equals((string) $meta['sha256'], $sha256)) {
            $this->registrar('error', $corte, $sha256, 0, $meta['origen'] ?? null, $cargaId, 'El archivo no coincide con el sha256 de la carga.');

            throw new InvalidArgumentException('El archivo no coincide con el sha256 de la carga.');
        }

        $existentes = PasivaRubro::query()->whereDate('corte', $corte)->where('sha256', $sha256)->count();

        if ($existentes > 0) {
            $this->registrar('omitida', $corte, $sha256, $existentes, $meta['origen'] ?? null, $cargaId, 'El mismo archivo ya estaba cargado para este corte.');

            return new ResultadoCargaPasiva('omitida', $existentes, $corte, $sha256);
        }

        try {
            $filas = $this->lector->leer($ruta, $extension);
        } catch (ValidationException $exception) {
            $mensaje = collect($exception->errors())->flatten()->implode(' ');
            $this->registrar('error', $corte, $sha256, 0, $meta['origen'] ?? null, $cargaId, $mensaje !== '' ? $mensaje : 'No se pudo leer la pasiva.');

            throw $exception;
        }
        $origen = $meta['origen'] ?? 'Secretaría de Hacienda — '.basename($ruta);
        $cargadoEn = now();

        DB::transaction(function () use ($filas, $corte, $sha256, $origen, $cargaId, $cargadoEn): void {
            foreach (array_chunk($filas, 400) as $lote) {
                PasivaRubro::query()->insert(array_map(
                    fn (array $fila): array => $fila + [
                        'corte' => $corte,
                        'sha256' => $sha256,
                        'pasiva_carga_id' => $cargaId,
                        'cargado_en' => $cargadoEn,
                        'origen' => $origen,
                    ],
                    $lote,
                ));
            }

            $this->registrar('ok', $corte, $sha256, count($filas), $origen, $cargaId);
        });

        return new ResultadoCargaPasiva('ok', count($filas), $corte, $sha256);
    }

    public function registrarError(PasivaCarga $carga, string $mensaje): void
    {
        $corte = $carga->seguimiento?->fecha_corte?->toDateString() ?? '1900-01-01';

        $this->registrar('error', $corte, (string) $carga->sha256, 0, 'Secretaría de Hacienda — '.$carga->nombre_original, $carga->id, $mensaje);
    }

    public function registrar(string $estado, ?string $corte, string $sha256, int $rubros, ?string $origen, ?int $pasivaCargaId, ?string $mensaje = null): void
    {
        AnaliticaSincronizacion::query()->create([
            'pasiva_carga_id' => $pasivaCargaId,
            'corte' => $corte !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $corte) === 1 ? $corte : null,
            'sha256' => $sha256,
            'estado' => $estado,
            'rubros' => $rubros,
            'origen' => $origen,
            'mensaje' => $mensaje === null ? null : mb_substr($mensaje, 0, 2000),
        ]);
    }
}
