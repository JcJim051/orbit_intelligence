<?php

namespace Database\Seeders;

use App\Services\Intelligence\IndicadoresTerritoriales\ImportadorMatrizIndicadores;
use Illuminate\Database\Seeder;

class IndicadoresTerritorialesSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/Matriz_consolidada_indicadores_PET.xlsx');
        $result = app(ImportadorMatrizIndicadores::class)->import($path);

        $this->command?->info(
            "Indicadores territoriales: {$result['created']} creados, {$result['updated']} actualizados y {$result['skipped']} conservados."
        );

        if ($result['unmatched_dependencies'] !== []) {
            $this->command?->warn(
                count($result['unmatched_dependencies']).' entidades externas quedaron conservadas como fuente textual, sin relación con una dependencia SIID.'
            );
        }
    }
}
