<?php

namespace App\Console\Commands;

use App\Models\PasivaCarga;
use App\Services\Analitica\SincronizadorPasivaVigente;
use Illuminate\Console\Command;

class SincronizarPasivasVigentes extends Command
{
    protected $signature = 'analitica:sincronizar-vigentes';

    protected $description = 'Sincroniza a analitica cada pasiva que hoy está marcada como vigente';

    public function handle(SincronizadorPasivaVigente $sincronizador): int
    {
        $cargas = PasivaCarga::query()->where('es_vigente', true)->orderBy('id')->get();

        if ($cargas->isEmpty()) {
            $this->info('No hay pasivas vigentes.');

            return self::SUCCESS;
        }

        foreach ($cargas as $carga) {
            $resultado = $sincronizador->sincronizar($carga);

            if ($resultado === null) {
                $this->warn('La carga '.$carga->id.' no se sincronizó. El detalle quedó en el registro del panel.');

                continue;
            }

            $this->info($carga->id.' '.$resultado->estado.' corte '.$resultado->corte.' rubros '.$resultado->rubros);
        }

        return self::SUCCESS;
    }
}
