<?php

namespace App\Services\GoogleDrive;

use App\Models\DriveConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveService
{
    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3';

    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('meetings.google.client_id'),
            'redirect_uri' => config('meetings.google.redirect_uri'),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'scope' => 'openid email https://www.googleapis.com/auth/drive.file',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('meetings.google.client_id'),
            'client_secret' => config('meetings.google.client_secret'),
            'redirect_uri' => config('meetings.google.redirect_uri'),
            'grant_type' => 'authorization_code',
        ])->throw();

        return $response->json();
    }

    public function userEmail(string $accessToken): ?string
    {
        return Http::withToken($accessToken)->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json('email');
    }

    public function ensureAccessToken(DriveConnection $connection): string
    {
        if ($connection->access_token && $connection->token_expires_at?->isFuture()) {
            return $connection->access_token;
        }
        if (! $connection->refresh_token) {
            throw new RuntimeException('La conexión de Google Drive debe autorizarse nuevamente.');
        }
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('meetings.google.client_id'),
            'client_secret' => config('meetings.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);
        if ($response->failed()) {
            $connection->update(['active' => false, 'last_error' => 'No fue posible renovar OAuth.']);
            throw new RuntimeException('No fue posible renovar la conexión de Google Drive.');
        }
        $connection->update([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600) - 60),
            'last_error' => null,
        ]);

        return $connection->access_token;
    }

    public function createRoot(DriveConnection $connection): string
    {
        $token = $this->ensureAccessToken($connection);

        return $this->createFolder($token, config('meetings.google.root_folder'), null);
    }

    public function ensurePath(DriveConnection $connection, string $path): string
    {
        $token = $this->ensureAccessToken($connection);
        $parent = $connection->root_folder_id ?: $this->createRoot($connection);
        if (! $connection->root_folder_id) {
            $connection->update(['root_folder_id' => $parent]);
        }
        foreach (array_filter(explode('/', $path)) as $part) {
            $parent = $this->findFolder($token, $part, $parent) ?: $this->createFolder($token, $part, $parent);
        }

        return $parent;
    }

    public function upload(DriveConnection $connection, string $localPath, string $name, string $mime, string $parentId, ?string $idempotencyKey = null): array
    {
        $token = $this->ensureAccessToken($connection);
        if ($idempotencyKey && $existing = $this->findByExportId($token, $parentId, $idempotencyKey)) {
            return $existing;
        }
        $size = filesize($localPath);
        $metadata = ['name' => $name, 'parents' => [$parentId]];
        if ($idempotencyKey) {
            $metadata['appProperties'] = ['actalab_export_id' => $idempotencyKey];
        }

        if ($size > 5 * 1024 * 1024) {
            $start = Http::withToken($token)->withHeaders([
                'X-Upload-Content-Type' => $mime,
                'X-Upload-Content-Length' => (string) $size,
            ])->post(self::UPLOAD.'/files?uploadType=resumable&fields=id,name,size,md5Checksum', $metadata);
            if ($start->failed() || ! $start->header('Location')) {
                throw new RuntimeException('Google Drive rechazó el inicio de la carga reanudable.');
            }
            $response = Http::withHeaders(['Content-Length' => (string) $size])
                ->withBody(fopen($localPath, 'r'), $mime)
                ->put($start->header('Location'));
        } else {
            $response = Http::withToken($token)
                ->attach('metadata', json_encode($metadata), 'metadata.json', ['Content-Type' => 'application/json; charset=UTF-8'])
                ->attach('file', fopen($localPath, 'r'), $name, ['Content-Type' => $mime])
                ->post(self::UPLOAD.'/files?uploadType=multipart&fields=id,name,size,md5Checksum');
        }
        if ($response->failed()) {
            throw new RuntimeException('Google Drive no pudo almacenar el archivo: '.$response->status());
        }

        return $response->json();
    }

    public function verify(DriveConnection $connection): bool
    {
        $response = $this->client($connection)->get(self::API.'/about', ['fields' => 'user']);
        $connection->update([
            'last_verified_at' => $response->successful() ? now() : null,
            'last_error' => $response->successful() ? null : 'La verificación de Drive falló.',
            'active' => $response->successful(),
        ]);

        return $response->successful();
    }

    private function client(DriveConnection $connection): PendingRequest
    {
        return Http::withToken($this->ensureAccessToken($connection))->timeout(120);
    }

    private function findFolder(string $token, string $name, string $parent): ?string
    {
        $name = str_replace(['\\', "'"], ['\\\\', "\\'"], $name);
        $response = Http::withToken($token)->get(self::API.'/files', [
            'q' => "name = '{$name}' and '{$parent}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'fields' => 'files(id,name)', 'pageSize' => 1,
        ])->throw();

        return data_get($response->json(), 'files.0.id');
    }

    private function findByExportId(string $token, string $parent, string $exportId): ?array
    {
        $exportId = str_replace(['\\', "'"], ['\\\\', "\\'"], $exportId);
        $response = Http::withToken($token)->get(self::API.'/files', [
            'q' => "'{$parent}' in parents and appProperties has { key='actalab_export_id' and value='{$exportId}' } and trashed = false",
            'fields' => 'files(id,name,size,md5Checksum)', 'pageSize' => 1,
        ])->throw();

        return data_get($response->json(), 'files.0');
    }

    private function createFolder(string $token, string $name, ?string $parent): string
    {
        $payload = ['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder'];
        if ($parent) {
            $payload['parents'] = [$parent];
        }

        return Http::withToken($token)->post(self::API.'/files?fields=id', $payload)->throw()->json('id');
    }
}
