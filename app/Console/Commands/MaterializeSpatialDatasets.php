<?php

namespace App\Console\Commands;

use App\Enums\DatasetStatus;
use App\Models\SpatialDataset;
use App\Services\Postgis\MaterializeSpatialDataset;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('geodata:materialize {slug? : Conjunto específico; si se omite procesa todos los activos}')]
#[Description('Crea o actualiza las tablas de captura PostGIS desde los formularios publicados')]
class MaterializeSpatialDatasets extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MaterializeSpatialDataset $materializer): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Este comando requiere PostgreSQL con PostGIS. La conexión SQLite no fue modificada.');

            return self::FAILURE;
        }

        $query = SpatialDataset::query()->where('status', DatasetStatus::Active->value);
        if (is_string($this->argument('slug'))) {
            $query->where('slug', $this->argument('slug'));
        }
        $datasets = $query->orderBy('name')->get();

        if ($datasets->isEmpty()) {
            $this->warn('No se encontraron conjuntos activos para materializar.');

            return self::SUCCESS;
        }

        $originalConnection = DB::getDefaultConnection();
        if ($originalConnection === 'managed_postgis' && config('database.connections.managed_postgis_admin')) {
            DB::setDefaultConnection('managed_postgis_admin');
            DB::purge('managed_postgis_admin');
        }

        try {
            $failed = false;
            foreach ($datasets as $dataset) {
                try {
                    $materializer->materialize($dataset);
                    $this->info("{$dataset->name}: capture.{$dataset->fresh()->physical_table} lista.");
                } catch (Throwable $exception) {
                    $failed = true;
                    $this->error("{$dataset->name}: {$exception->getMessage()}");
                }
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge('managed_postgis_admin');
        }
    }
}
