<?php

namespace App\Console\Commands;

use App\Services\Siid\SincronizadorListadoSiid;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('siid:sincronizar-listado {--dry-run : Diagnostica sin modificar el catálogo} {--apply : Aplica los cambios al catálogo} {--origin=manual : Origen de la ejecución} {--user= : Usuario que inició la ejecución}')]
#[Description('Sincroniza el listado maestro de indicadores SIID desde Google Sheets')]
class SincronizarListadoSiid extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SincronizadorListadoSiid $synchronizer): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Use solo una opción: --dry-run o --apply.');

            return self::INVALID;
        }

        $apply = (bool) $this->option('apply');
        $sync = $synchronizer->execute($apply, (string) $this->option('origin'), $this->option('user') ? (int) $this->option('user') : null);
        $this->table(['Estado', 'Leídos', 'Creados', 'Actualizados', 'Inactivados', 'Conflictos'], [[
            $sync->estado, $sync->leidos, $sync->creados, $sync->actualizados, $sync->inactivados, $sync->conflictos,
        ]]);

        if ($sync->estado === 'fallido') {
            $this->error((string) $sync->error_general);

            return self::FAILURE;
        }

        $this->info($apply ? 'Sincronización aplicada.' : 'Diagnóstico terminado sin modificar datos.');

        return self::SUCCESS;
    }
}
