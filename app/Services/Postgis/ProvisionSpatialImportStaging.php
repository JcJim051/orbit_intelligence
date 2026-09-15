<?php

namespace App\Services\Postgis;

use App\Models\SpatialImport;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProvisionSpatialImportStaging
{
    public function __construct(private ManagedPostgisConfiguration $configuration) {}

    public function provision(SpatialImport $import): void
    {
        $connection = $this->adminConnection();
        $role = $this->quoteIdentifier($import->database_username);
        $schema = $this->quoteIdentifier($import->staging_schema);
        $admin = $this->quoteIdentifier((string) $this->configuration->load()['admin_username']);
        $password = $connection->getPdo()->quote($import->database_password);
        $validUntil = $connection->getPdo()->quote($import->expires_at->utc()->toIso8601String());

        if (! is_string($password) || ! is_string($validUntil)) {
            throw new RuntimeException('No fue posible proteger la credencial temporal de QGIS.');
        }

        $exists = $connection->selectOne('SELECT 1 AS present FROM pg_roles WHERE rolname = ?', [$import->database_username]);
        $connection->statement(($exists === null ? 'CREATE ROLE ' : 'ALTER ROLE ').$role.' WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOINHERIT PASSWORD '.$password.' VALID UNTIL '.$validUntil);
        // QGIS owns the tables it creates. Make the managed administrator a
        // member so Laravel can inspect and later freeze those tables.
        $connection->statement("GRANT {$role} TO {$admin}");
        $connection->statement('GRANT CONNECT ON DATABASE '.$this->quoteIdentifier((string) $this->configuration->load()['database'])." TO {$role}");
        $connection->statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
        $connection->statement("REVOKE ALL ON SCHEMA {$schema} FROM PUBLIC");
        $connection->statement("GRANT USAGE, CREATE ON SCHEMA {$schema} TO {$role}");
        $connection->statement("GRANT USAGE ON SCHEMA public TO {$role}");
        $connection->statement("GRANT SELECT ON public.geometry_columns, public.spatial_ref_sys TO {$role}");
        $connection->statement("ALTER ROLE {$role} SET search_path TO {$schema}, public");

        foreach (['capture', 'publication'] as $protectedSchema) {
            if ($connection->selectOne('SELECT 1 AS present FROM information_schema.schemata WHERE schema_name = ?', [$protectedSchema])) {
                $connection->statement('REVOKE ALL ON SCHEMA '.$this->quoteIdentifier($protectedSchema)." FROM {$role}");
            }
        }
    }

    public function freeze(SpatialImport $import): void
    {
        $connection = $this->adminConnection();
        $role = $this->quoteIdentifier($import->database_username);
        $schema = $this->quoteIdentifier($import->staging_schema);
        $owner = $this->quoteIdentifier((string) $this->configuration->load()['admin_username']);
        $tables = $connection->select(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'",
            [$import->staging_schema],
        );

        $connection->statement("REVOKE CREATE ON SCHEMA {$schema} FROM {$role}");
        foreach ($tables as $table) {
            $qualified = $schema.'.'.$this->quoteDatabaseIdentifier((string) $table->table_name);
            $connection->statement("ALTER TABLE {$qualified} OWNER TO {$owner}");
            $connection->statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$qualified} TO {$role}");
        }
    }

    private function adminConnection(): Connection
    {
        $data = $this->configuration->load() ?? throw new RuntimeException('Configure primero PostgreSQL desde Infraestructura SIG.');
        $this->configuration->configureConnections($data);
        DB::purge('managed_postgis_admin');
        $connection = DB::connection('managed_postgis_admin');
        $connection->selectOne('SELECT current_database() AS database');

        return $connection;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $identifier)) {
            throw new RuntimeException('El identificador temporal de PostgreSQL no es válido.');
        }

        return '"'.$identifier.'"';
    }

    private function quoteDatabaseIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
