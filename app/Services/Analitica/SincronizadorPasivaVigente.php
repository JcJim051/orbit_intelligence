<?php

namespace App\Services\Analitica;

use App\Models\PasivaCarga;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SincronizadorPasivaVigente
{
    public function __construct(private readonly ImportadorPasivaAnalitica $importador) {}

    public function sincronizar(PasivaCarga $carga): ?ResultadoCargaPasiva
    {
        $carga->loadMissing('seguimiento');

        if (! $carga->es_vigente) {
            return null;
        }

        $corte = $carga->seguimiento?->fecha_corte?->toDateString();

        if ($corte === null) {
            $this->importador->registrarError($carga, 'La carga vigente no tiene fecha de corte.');

            return null;
        }

        $disco = Storage::disk($carga->disk);

        if (! $disco->exists($carga->path)) {
            $this->importador->registrarError($carga, 'No se encontró el archivo de la pasiva en el disco.');

            return null;
        }

        try {
            return $this->importador->importar(
                $disco->path($carga->path),
                strtolower(pathinfo($carga->nombre_original, PATHINFO_EXTENSION) ?: 'xlsx'),
                $corte,
                [
                    'pasiva_carga_id' => $carga->id,
                    'sha256' => $carga->sha256,
                    'origen' => 'Secretaría de Hacienda — '.$carga->nombre_original,
                ],
            );
        } catch (ValidationException|InvalidArgumentException) {
            return null;
        }
    }
}
