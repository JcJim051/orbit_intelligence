<?php

namespace App\Services\Postgis;

use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use RuntimeException;

class EncryptedPostgisStore
{
    private string $path;

    private Encrypter $encrypter;

    private Filesystem $files;

    public function __construct(?string $path = null, ?Encrypter $encrypter = null, ?Filesystem $files = null)
    {
        $this->path = $path ?? storage_path('app/private/postgis-connection.enc');
        $this->encrypter = $encrypter ?? $this->makeEncrypter();
        $this->files = $files ?? new Filesystem;
    }

    public function exists(): bool
    {
        return $this->files->exists($this->path);
    }

    /** @return array<string, mixed>|null */
    public function read(): ?array
    {
        if (! $this->exists()) {
            return null;
        }

        try {
            $contents = $this->encrypter->decryptString($this->files->get($this->path));
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('La configuración PostGIS cifrada no contiene JSON válido.', previous: $exception);
        }

        if (! is_array($data)) {
            throw new RuntimeException('La configuración PostGIS cifrada no tiene una estructura válida.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function write(array $data): void
    {
        $this->files->ensureDirectoryExists(dirname($this->path), 0700);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->files->put($this->path, $this->encrypter->encryptString($json), true);
        chmod($this->path, 0600);
    }

    private function makeEncrypter(): Encrypter
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(mb_substr($key, 7), true);
            $key = is_string($decoded) ? $decoded : '';
        }
        if ($key === '') {
            throw new RuntimeException('APP_KEY debe existir para cifrar las credenciales PostGIS.');
        }

        return new Encrypter($key, (string) config('app.cipher'));
    }
}
