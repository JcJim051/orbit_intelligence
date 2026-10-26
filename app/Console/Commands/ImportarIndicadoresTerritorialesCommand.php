<?php

namespace App\Console\Commands;

use App\Services\Intelligence\IndicadoresTerritoriales\ImportadorMatrizIndicadores;
use Illuminate\Console\Command;
use Throwable;

class ImportarIndicadoresTerritorialesCommand extends Command
{
    protected $signature = 'indicadores-territoriales:importar {archivo? : Ruta opcional al archivo Matriz_consolidada_indicadores_PET.xlsx}';

    protected $description = 'Importa de forma idempotente las fichas PET y conserva las que ya entraron en revisión';

    public function handle(ImportadorMatrizIndicadores $importer): int
    {
        $path = (string) ($this->argument('archivo') ?: database_path('seeders/data/Matriz_consolidada_indicadores_PET.xlsx'));

        try {
            $result = $importer->import($path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Resultado', 'Cantidad'], [
            ['Archivo', $path],
            ['Creados', $result['created']],
            ['Actualizados', $result['updated']],
            ['Omitidos por estar en revisión', $result['skipped']],
            ['Filas válidas', $result['total']],
            ['Dependencias sin emparejar', count($result['unmatched_dependencies'])],
        ]);

        if ($result['unmatched_dependencies'] !== []) {
            $this->warn('Dependencias sin emparejar automáticamente:');
            foreach ($result['unmatched_dependencies'] as $dependency) {
                $this->line(' - '.$dependency);
            }
        }

        return self::SUCCESS;
    }
}
