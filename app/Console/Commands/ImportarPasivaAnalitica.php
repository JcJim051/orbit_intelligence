<?php

namespace App\Console\Commands;

use App\Services\Analitica\ImportadorPasivaAnalitica;
use Illuminate\Console\Command;

class ImportarPasivaAnalitica extends Command
{
    protected $signature = 'analitica:importar-pasiva {archivo} {--corte=}';

    protected $description = 'Carga la pasiva de Hacienda en el esquema analitica y la deja lista para Superset';

    public function handle(ImportadorPasivaAnalitica $importador): int
    {
        $archivo = (string) $this->argument('archivo');

        if (! is_file($archivo)) {
            $this->error('No se encontró el archivo de la pasiva.');

            return self::FAILURE;
        }

        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        $total = $importador->importar($archivo, $extension, $this->option('corte') ? (string) $this->option('corte') : null);
        $this->info('Se cargaron '.$total.' rubros en analitica.pasiva_rubro.');

        return self::SUCCESS;
    }
}
