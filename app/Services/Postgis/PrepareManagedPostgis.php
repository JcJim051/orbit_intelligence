<?php

namespace App\Services\Postgis;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PrepareManagedPostgis
{
    public function __construct(private ManagedPostgisConfiguration $configuration) {}

    /** @return array<string, string> */
    public function handle(): array
    {
        return Cache::lock('managed-postgis-preparation', 300)->block(5, function (): array {
            $summary = $this->configuration->summary();
            if (! $summary['configured']) {
                throw new RuntimeException('Primero pruebe y guarde la conexión PostgreSQL.');
            }
            $outputs = [];
            $originalDefault = DB::getDefaultConnection();

            try {
                $this->run('migrate', [
                    '--database' => 'managed_postgis_admin',
                    '--force' => true,
                ], $outputs);
                DB::setDefaultConnection('managed_postgis_admin');
                DB::purge('managed_postgis_admin');
                $this->run('geodata:materialize', [], $outputs);
                $this->configuration->markPrepared();
            } finally {
                DB::setDefaultConnection($originalDefault);
                DB::purge('managed_postgis_admin');
            }

            return $outputs;
        });
    }

    /** @param array<string, mixed> $arguments
     * @param  array<string, string>  $outputs
     */
    private function run(string $command, array $arguments, array &$outputs): void
    {
        $exitCode = Artisan::call($command, $arguments);
        $outputs[$command] = Artisan::output();
        if ($exitCode !== 0) {
            throw new RuntimeException("Falló {$command}. Revise el resultado técnico antes de continuar.");
        }
    }
}
