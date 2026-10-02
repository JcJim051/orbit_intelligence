<?php

namespace App\Console\Commands;

use App\Services\Ods\ImportSinergiaOdsCatalog;
use Illuminate\Console\Command;
use Throwable;

class ImportSinergiaOdsCatalogCommand extends Command
{
    protected $signature = 'ods:import-sinergia {--source= : URL JS alternativa para pruebas}';

    protected $description = 'Importa el catálogo de indicadores ODS Colombia desde la tabla JS pública de Sinergia DNP';

    public function handle(ImportSinergiaOdsCatalog $importer): int
    {
        try {
            $result = $importer->import($this->option('source') ?: null);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Catálogo ODS importado: {$result['goals']} objetivos, {$result['targets']} metas, {$result['indicators']} indicadores.");

        return self::SUCCESS;
    }
}
