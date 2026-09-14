<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CopySqliteToPostgis extends Command
{
    protected $signature = 'database:copy-sqlite-to-postgis
        {--chunk=500 : Filas copiadas por lote}
        {--replace-existing : Reemplaza transaccionalmente la copia administrativa existente}';

    protected $description = 'Copia los datos persistentes de SQLite a un PostGIS ya migrado y vacío';

    /**
     * Orden topológico según las claves foráneas de la aplicación.
     * Cachés, sesiones, colas y la tabla migrations se regeneran en destino.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'users',
        'password_reset_tokens',
        'personal_access_tokens',
        'drive_connections',
        'meetings',
        'meeting_files',
        'transcripts',
        'transcript_segments',
        'meeting_summaries',
        'action_items',
        'drive_exports',
        'audit_logs',
        'investment_sync_runs',
        'investment_source_snapshots',
        'investment_projects',
        'investment_financials',
        'investment_locations',
        'investment_beneficiaries',
        'investment_products',
        'investment_contracts',
        'investment_policy_focuses',
        'investment_territorial_resources',
        'investment_project_meeting',
        'project_decisions',
        'investment_progress_reports',
        'investment_entities',
        'investment_entity_assignments',
        'geo_viewers',
        'geo_layers',
        'geo_viewer_layers',
        'spatial_datasets',
        'dataset_form_versions',
        'dataset_form_fields',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = DB::connection('legacy_sqlite');
        $destination = DB::connection('managed_postgis_admin');

        if ($source->getDriverName() !== 'sqlite' || $destination->getDriverName() !== 'pgsql') {
            $this->error('Se requieren las conexiones legacy_sqlite (SQLite) y managed_postgis_admin (PostgreSQL).');

            return self::FAILURE;
        }

        $chunk = max(50, min((int) $this->option('chunk'), 5000));

        try {
            $this->validateSchemas($source, $destination);
            $destination->disableQueryLog();
            $destination->transaction(function () use ($source, $destination, $chunk): void {
                $this->prepareDestination($destination);

                foreach (self::TABLES as $table) {
                    $count = $source->table($table)->count();
                    if ($count === 0) {
                        $this->line("{$table}: 0 filas.");

                        continue;
                    }

                    $copied = 0;
                    $source->table($table)->orderBy($this->orderingColumn($source, $table))->chunk($chunk, function ($rows) use ($destination, $table, &$copied): void {
                        $payload = $rows->map(fn (object $row): array => (array) $row)->all();
                        $destination->table($table)->insert($payload);
                        $copied += count($payload);
                    });
                    $this->info("{$table}: {$copied} filas copiadas.");
                }

                $this->resetSerialSequences($destination);
            });
            $this->verifyRowCounts($source, $destination);
        } catch (Throwable $exception) {
            $this->error('Copia cancelada: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Copia completa. SQLite sigue siendo la conexión activa; aún no se ha realizado el corte final.');

        return self::SUCCESS;
    }

    private function validateSchemas(Connection $source, Connection $destination): void
    {
        foreach (self::TABLES as $table) {
            if (! $source->getSchemaBuilder()->hasTable($table)) {
                throw new \RuntimeException("Falta la tabla {$table} en SQLite.");
            }
            if (! $destination->getSchemaBuilder()->hasTable($table)) {
                throw new \RuntimeException("Falta la tabla {$table} en PostgreSQL. Prepárela primero desde Infraestructura SIG.");
            }
        }
    }

    private function prepareDestination(Connection $destination): void
    {
        $hasData = false;
        foreach (self::TABLES as $table) {
            if ($destination->table($table)->exists()) {
                $hasData = true;
                break;
            }
        }

        if (! $hasData) {
            return;
        }

        if (! $this->option('replace-existing')) {
            throw new \RuntimeException('PostgreSQL ya contiene datos administrativos; use --replace-existing sólo para una preparación controlada.');
        }

        $tables = collect(self::TABLES)
            ->map(fn (string $table): string => '"'.str_replace('"', '""', $table).'"')
            ->implode(', ');
        $destination->statement("TRUNCATE TABLE {$tables} RESTART IDENTITY CASCADE");
        $this->warn('Se reemplazó la copia administrativa anterior. Las capas capture.* no fueron eliminadas.');
    }

    private function orderingColumn(Connection $connection, string $table): string
    {
        return $connection->getSchemaBuilder()->hasColumn($table, 'id') ? 'id' : 'email';
    }

    private function resetSerialSequences(Connection $destination): void
    {
        foreach (self::TABLES as $table) {
            if (! $destination->getSchemaBuilder()->hasColumn($table, 'id')) {
                continue;
            }

            $qualifiedTable = 'public.'.$table;
            $sequence = $destination->selectOne("SELECT pg_get_serial_sequence(?, 'id') AS sequence", [$qualifiedTable])?->sequence;
            if (! is_string($sequence)) {
                continue;
            }

            $quotedTable = '"public"."'.str_replace('"', '""', $table).'"';
            $destination->statement(
                "SELECT setval(?::regclass, GREATEST(COALESCE(MAX(\"id\"), 1), 1), MAX(\"id\") IS NOT NULL) FROM {$quotedTable}",
                [$sequence],
            );
        }
    }

    private function verifyRowCounts(Connection $source, Connection $destination): void
    {
        foreach (self::TABLES as $table) {
            $sourceCount = $source->table($table)->count();
            $destinationCount = $destination->table($table)->count();
            if ($sourceCount !== $destinationCount) {
                throw new \RuntimeException("Verificación fallida en {$table}: SQLite={$sourceCount}, PostgreSQL={$destinationCount}.");
            }
        }
    }
}
