<?php

namespace App\Console\Commands;

use App\Services\Superset\SincronizadorDatasets;
use Illuminate\Console\Command;
use RuntimeException;

class SincronizarDatasetsSuperset extends Command
{
    protected $signature = 'analitica:sincronizar-datasets';

    protected $description = 'Crea o refresca en Superset un dataset por cada vista del esquema analitica';

    public function handle(SincronizadorDatasets $sincronizador): int
    {
        try {
            $resultado = $sincronizador->sincronizar();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Datasets creados: '.($resultado['creados'] === [] ? 'ninguno' : implode(', ', $resultado['creados'])));
        $this->info('Datasets refrescados: '.($resultado['actualizados'] === [] ? 'ninguno' : implode(', ', $resultado['actualizados'])));
        $this->line('El tablero conserva su UUID. Las vistas vigentes cambian de filas, no de columnas, en cada carga.');

        return self::SUCCESS;
    }
}
