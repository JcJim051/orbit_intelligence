<?php

namespace App\Services\Siid;

use App\Models\SiidGoogleSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class GoogleSheetsServiceAccountClient
{
    /** @return array{complete: bool, sheets: array<string, array<int, array<int, mixed>>>, complementary: array<string, array<int, array<int, mixed>>>, warnings: array<int, string>} */
    public function snapshot(): array
    {
        $settings = $this->settings();
        $spreadsheetId = (string) ($settings?->spreadsheet_id ?: config('siid.sheets.spreadsheet_id'));
        $tabs = $settings?->main_tabs ?: config('siid.sheets.main_tabs', []);

        if ($spreadsheetId === '') {
            throw new RuntimeException('Configure el ID del documento en Administración > Conexión Google.');
        }
        if ($tabs === []) {
            throw new RuntimeException('Configure al menos una pestaña principal en Administración > Conexión Google.');
        }

        $http = $this->http();
        $metadata = $http->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}", [
            'fields' => 'sheets.properties.title',
        ])->throw()->json();
        $available = collect($metadata['sheets'] ?? [])->pluck('properties.title')->filter()->values();
        $missing = collect($tabs)->reject(fn (string $tab): bool => $available->contains($tab))->values();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException('No se encontraron las pestañas principales: '.$missing->implode(', '));
        }

        $sheets = [];
        foreach ($tabs as $tab) {
            $range = rawurlencode("'{$tab}'");
            $response = $http->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}", [
                'majorDimension' => 'ROWS',
            ])->throw()->json();
            $sheets[$tab] = $response['values'] ?? [];
        }

        $warnings = [];
        $complementary = [];
        foreach ($settings?->complementary_tabs ?: config('siid.sheets.complementary_tabs', []) as $tab) {
            if (! $available->contains($tab)) {
                $warnings[] = "No se encontró la pestaña complementaria {$tab}.";

                continue;
            }
            $range = rawurlencode("'{$tab}'");
            $response = $http->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}", [
                'majorDimension' => 'ROWS',
            ])->throw()->json();
            $complementary[$tab] = $response['values'] ?? [];
        }

        return ['complete' => count($sheets) === count($tabs), 'sheets' => $sheets, 'complementary' => $complementary, 'warnings' => $warnings];
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->withToken($this->accessToken())->timeout(45)->retry(2, 500);
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();
        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64Url(json_encode([
            'iss' => $credentials['client_email'] ?? null,
            'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $payload = $header.'.'.$claims;

        if (! openssl_sign($payload, $signature, (string) ($credentials['private_key'] ?? ''), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No fue posible firmar la credencial de Google Sheets.');
        }

        $assertion = $payload.'.'.$this->base64Url($signature);
        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ])->throw()->json();

        return (string) ($response['access_token'] ?? throw new RuntimeException('Google no devolvió un token de acceso.'));
    }

    /** @return array<string, mixed> */
    private function credentials(): array
    {
        $settings = $this->settings();
        if (filled($settings?->credentials_encrypted)) {
            return json_decode(Crypt::decryptString($settings->credentials_encrypted), true, 512, JSON_THROW_ON_ERROR);
        }

        $inline = config('siid.sheets.credentials_json');
        $path = config('siid.sheets.credentials_path');
        $json = filled($inline) ? (string) $inline : (filled($path) && is_file((string) $path) ? file_get_contents((string) $path) : false);

        if (! is_string($json) || $json === '') {
            throw new RuntimeException('No se encontraron las credenciales de la cuenta de servicio de Google.');
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function settings(): ?SiidGoogleSetting
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql' || ! Schema::hasTable('siid_publicacion.google_settings')) {
            return null;
        }

        return SiidGoogleSetting::query()->first();
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
