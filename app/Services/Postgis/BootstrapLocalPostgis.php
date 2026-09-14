<?php

namespace App\Services\Postgis;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class BootstrapLocalPostgis
{
    public function __construct(
        private LocalPostgisRuntime $runtime,
        private ManagedPostgisConfiguration $configuration,
        private Filesystem $files,
    ) {}

    /** @return array{host: string, port: int, database: string, username: string, password: string, sslmode: string} */
    public function handle(): array
    {
        if ($this->configuration->exists()) {
            throw new RuntimeException('PostGIS ya tiene una configuración guardada.');
        }

        $ownerPassword = $this->ownerPassword();
        $qgisPassword = $this->password();
        $data = [
            'host' => '127.0.0.1',
            'port' => 55432,
            'database' => 'siid_meta',
            'sslmode' => 'prefer',
            'admin_username' => 'siid_owner',
            'admin_password' => $ownerPassword,
            'app_username' => 'siid_app',
            'app_password' => $this->password(),
            'qgis_username' => 'qgis_editor',
            'qgis_password' => $qgisPassword,
            'reader_username' => 'geoserver_reader',
            'reader_password' => $this->password(),
        ];

        $this->runtime->start();
        $this->configuration->testAndStore($data);

        return [
            'host' => $data['host'],
            'port' => $data['port'],
            'database' => $data['database'],
            'username' => $data['qgis_username'],
            'password' => $qgisPassword,
            'sslmode' => $data['sslmode'],
        ];
    }

    private function ownerPassword(): string
    {
        $path = base_path('deploy/postgis/secrets/postgres_owner_password');
        if ($this->files->exists($path)) {
            $password = trim($this->files->get($path));
            if (mb_strlen($password) < 16) {
                throw new RuntimeException('La clave local de PostGIS existe, pero no cumple la longitud mínima.');
            }

            return $password;
        }

        $this->files->ensureDirectoryExists(dirname($path), 0700);
        $password = $this->password();
        $this->files->put($path, $password.PHP_EOL, true);
        chmod($path, 0600);

        return $password;
    }

    private function password(): string
    {
        return Str::password(40, letters: true, numbers: true, symbols: false, spaces: false);
    }
}
