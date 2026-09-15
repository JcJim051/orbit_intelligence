<?php

namespace App\Services\Postgis;

use App\Models\SpatialImport;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SpatialImportProfiler
{
    public function __construct(private ManagedPostgisConfiguration $configuration) {}

    /** @return array{tables: array<int, array<string, mixed>>, profiled_at: string} */
    public function profile(SpatialImport $import): array
    {
        $connection = $this->connection();
        $this->ensureInspectionAccess($connection, $import);
        $connection->statement('SET ROLE '.$this->quoteIdentifier($import->database_username));

        try {
            $tables = $connection->select(
                "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE' ORDER BY table_name",
                [$import->staging_schema],
            );

            $profiled = collect($tables)->map(function (object $table) use ($connection, $import): array {
                $name = (string) $table->table_name;
                $columns = $connection->select(
                    'SELECT column_name, data_type, udt_name, is_nullable FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position',
                    [$import->staging_schema, $name],
                );
                $geometries = $connection->select(
                    'SELECT f_geometry_column AS column_name, type, srid FROM public.geometry_columns WHERE f_table_schema = ? AND f_table_name = ?',
                    [$import->staging_schema, $name],
                );
                $qualified = $this->quoteIdentifier($import->staging_schema).'.'.$this->quoteIdentifier($name);
                $rowCount = (int) $connection->scalar("SELECT COUNT(*) FROM {$qualified}");

                return [
                    'name' => $name,
                    'row_count' => $rowCount,
                    'columns' => collect($columns)->map(fn (object $column): array => [
                        'name' => (string) $column->column_name,
                        'data_type' => (string) $column->data_type,
                        'udt_name' => (string) $column->udt_name,
                        'nullable' => $column->is_nullable === 'YES',
                    ])->all(),
                    'geometries' => collect($geometries)->map(fn (object $geometry): array => [
                        'column' => (string) $geometry->column_name,
                        'type' => (string) $geometry->type,
                        'srid' => (int) $geometry->srid,
                    ])->all(),
                ];
            })->all();

            return ['tables' => $profiled, 'profiled_at' => now()->toIso8601String()];
        } finally {
            $connection->statement('RESET ROLE');
        }
    }

    private function ensureInspectionAccess(Connection $connection, SpatialImport $import): void
    {
        $data = $this->configuration->load() ?? throw new RuntimeException('Configure primero PostgreSQL desde Infraestructura SIG.');
        $temporaryRole = $this->quoteIdentifier($import->database_username);
        $adminRole = $this->quoteIdentifier((string) $data['admin_username']);

        // Repairs authorizations created before the administrator membership
        // was provisioned automatically.
        $connection->statement("GRANT {$temporaryRole} TO {$adminRole}");
    }

    private function connection(): Connection
    {
        $data = $this->configuration->load() ?? throw new RuntimeException('Configure primero PostgreSQL desde Infraestructura SIG.');
        $this->configuration->configureConnections($data);
        DB::purge('managed_postgis_admin');

        return DB::connection('managed_postgis_admin');
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
