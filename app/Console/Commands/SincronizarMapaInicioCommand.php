<?php

namespace App\Console\Commands;

use App\Services\Micrositio\Mapa\SincronizarCapasMapaInicio;
use Illuminate\Console\Command;

class SincronizarMapaInicioCommand extends Command
{
    protected $signature = 'micrositio:sincronizar-mapa-inicio {--fuente=todas : todas, petroleo o agricultura}';

    protected $description = 'Actualiza las capas productivas del mapa inicial desde las fuentes oficiales';

    public function handle(SincronizarCapasMapaInicio $sincronizador): int
    {
        $resultados = $sincronizador->sincronizar((string) $this->option('fuente'));
        $fallidas = false;

        foreach ($resultados as $fuente => $resultado) {
            if ($resultado['estado'] === 'exitosa') {
                $this->info($fuente.': actualizado a '.$resultado['periodo']);

                continue;
            }

            $fallidas = true;
            $this->error($fuente.': '.$resultado['error']);
        }

        return $fallidas ? self::FAILURE : self::SUCCESS;
    }
}
