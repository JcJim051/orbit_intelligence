<?php

namespace App\Services\Postgis;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ManagedPostgisConfiguration
{
    public function __construct(private ?EncryptedPostgisStore $encryptedStore = null) {}

    public function exists(): bool
    {
        return $this->encryptedStore()->exists();
    }

    /** @return array<string, mixed>|null */
    public function load(): ?array
    {
        return $this->encryptedStore()->read();
    }

    /** @param array<string, mixed> $data */
    public function testAndStore(array $data): void
    {
        $candidate = [
            ...$data,
            'active' => false,
            'prepared_at' => null,
            'configured_at' => now()->toIso8601String(),
        ];
        $this->configureConnections($candidate);
        DB::purge('managed_postgis_admin');
        try {
            $connection = DB::connection('managed_postgis_admin');
            $connection->selectOne('SELECT current_database() AS database, version() AS version');
            $this->provisionRoles($connection, $candidate);
            $this->persist($candidate);
        } finally {
            DB::purge('managed_postgis_admin');
        }
    }

    public function apply(): void
    {
        $data = $this->load();
        if ($data === null) {
            return;
        }

        $this->configureConnections($data);
        if (($data['active'] ?? false) === true) {
            config(['database.default' => 'managed_postgis']);
        }
    }

    public function markPrepared(): void
    {
        $data = $this->required();
        $data['prepared_at'] = now()->toIso8601String();
        $this->persist($data);
    }

    public function markUnprepared(): void
    {
        $data = $this->required();
        $data['prepared_at'] = null;
        unset($data['activated_at']);
        $this->persist($data);
    }

    public function activate(): void
    {
        $data = $this->required();
        if (empty($data['prepared_at'])) {
            throw new RuntimeException('Primero debe preparar y verificar PostgreSQL.');
        }
        $data['active'] = true;
        $data['activated_at'] = now()->toIso8601String();
        $this->persist($data);
    }

    public function deactivate(): void
    {
        $data = $this->required();
        $data['active'] = false;
        $data['deactivated_at'] = now()->toIso8601String();
        $this->persist($data);
    }

    /** @return array{host: string, port: int, database: string, username: string, password: string, sslmode: string} */
    public function rotateQgisPassword(): array
    {
        $data = $this->required();
        $data['qgis_password'] = Str::password(40, letters: true, numbers: true, symbols: false, spaces: false);
        $data['qgis_credentials_rotated_at'] = now()->toIso8601String();
        $this->configureConnections($data);
        DB::purge('managed_postgis_admin');

        try {
            $connection = DB::connection('managed_postgis_admin');
            $connection->selectOne('SELECT current_database() AS database');
            $this->provisionRoles($connection, $data);
            $this->persist($data);
        } finally {
            DB::purge('managed_postgis_admin');
        }

        return [
            'host' => (string) $data['host'],
            'port' => (int) $data['port'],
            'database' => (string) $data['database'],
            'username' => (string) $data['qgis_username'],
            'password' => (string) $data['qgis_password'],
            'sslmode' => (string) $data['sslmode'],
        ];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $data = $this->load() ?? [];

        return [
            'configured' => $data !== [],
            'active' => (bool) ($data['active'] ?? false),
            'prepared_at' => $data['prepared_at'] ?? null,
            'activated_at' => $data['activated_at'] ?? null,
            'host' => $data['host'] ?? null,
            'port' => $data['port'] ?? null,
            'database' => $data['database'] ?? null,
            'sslmode' => $data['sslmode'] ?? null,
            'admin_username' => $data['admin_username'] ?? null,
            'app_username' => $data['app_username'] ?? null,
            'qgis_username' => $data['qgis_username'] ?? null,
            'reader_username' => $data['reader_username'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data */
    public function configureConnections(array $data): void
    {
        config([
            'database.connections.managed_postgis' => $this->connection($data, 'app'),
            'database.connections.managed_postgis_admin' => $this->connection($data, 'admin'),
            'database.managed_roles.qgis' => $data['qgis_username'],
            'database.managed_roles.reader' => $data['reader_username'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function persist(array $data): void
    {
        $this->encryptedStore()->write($data);
    }

    /** @return array<string, mixed> */
    private function required(): array
    {
        return $this->load() ?? throw new RuntimeException('No existe una configuración PostGIS guardada.');
    }

    /** @param array<string, mixed> $data */
    private function connection(array $data, string $role): array
    {
        return [
            'driver' => 'pgsql',
            'host' => $data['host'],
            'port' => (string) $data['port'],
            'database' => $data['database'],
            'username' => $data[$role.'_username'],
            'password' => $data[$role.'_password'],
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => $data['sslmode'],
        ];
    }

    private function encryptedStore(): EncryptedPostgisStore
    {
        return $this->encryptedStore ??= new EncryptedPostgisStore;
    }

    /** @param array<string, mixed> $data */
    private function provisionRoles(Connection $connection, array $data): void
    {
        foreach (['app', 'qgis', 'reader'] as $role) {
            $username = (string) $data[$role.'_username'];
            $password = (string) $data[$role.'_password'];
            $identifier = $this->quoteIdentifier($username);
            $passwordLiteral = $connection->getPdo()->quote($password);
            if (! is_string($passwordLiteral)) {
                throw new RuntimeException('No fue posible proteger una credencial PostgreSQL.');
            }

            $exists = $connection->selectOne('SELECT 1 AS present FROM pg_roles WHERE rolname = ?', [$username]);
            $connection->statement(($exists === null ? 'CREATE ROLE ' : 'ALTER ROLE ').$identifier.' WITH LOGIN PASSWORD '.$passwordLiteral);
            $database = $this->quoteIdentifier((string) $data['database']);
            $connection->statement("GRANT CONNECT ON DATABASE {$database} TO {$identifier}");
        }

        $appRole = $this->quoteIdentifier((string) $data['app_username']);
        $connection->statement("GRANT USAGE ON SCHEMA public TO {$appRole}");
        $connection->statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$appRole}");
        $connection->statement("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO {$appRole}");
        $connection->statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$appRole}");
        $connection->statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO {$appRole}");
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $identifier)) {
            throw new RuntimeException('La configuración contiene un identificador PostgreSQL no válido.');
        }

        return '"'.$identifier.'"';
    }
}
