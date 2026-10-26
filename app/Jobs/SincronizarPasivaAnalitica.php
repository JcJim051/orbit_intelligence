<?php

namespace App\Jobs;

use App\Models\PasivaCarga;
use App\Services\Analitica\ImportadorPasivaAnalitica;
use App\Services\Analitica\SincronizadorPasivaVigente;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SincronizarPasivaAnalitica implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $pasivaCargaId) {}

    public function uniqueId(): string
    {
        return (string) $this->pasivaCargaId;
    }

    public function handle(SincronizadorPasivaVigente $sincronizador): void
    {
        $carga = PasivaCarga::query()->find($this->pasivaCargaId);

        if ($carga === null) {
            return;
        }

        $sincronizador->sincronizar($carga);
    }

    public function failed(?Throwable $exception): void
    {
        $carga = PasivaCarga::query()->find($this->pasivaCargaId);

        if ($carga === null) {
            return;
        }

        app(ImportadorPasivaAnalitica::class)->registrarError(
            $carga,
            'No se pudo sincronizar la pasiva a analitica.',
        );
    }
}
