<?php

namespace App\Filament\Pages;

use App\Models\SiidGoogleSetting;
use App\Services\Siid\GoogleSheetsServiceAccountClient;
use App\Services\Siid\SincronizadorListadoSiid;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use JsonException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

class GoogleConnection extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $title = 'Conexión Google';

    protected static ?string $slug = 'administracion/conexion-google';

    protected string $view = 'filament.pages.google-connection';

    public string $spreadsheetId = '';

    public string $mainTabs = '';

    public string $complementaryTabs = '';

    public TemporaryUploadedFile|string|null $credentialsFile = null;

    public ?string $credentialsFilename = null;

    public ?string $serviceAccountEmail = null;

    public ?string $lastStatus = null;

    public ?string $lastError = null;

    public ?string $lastTestedAt = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $settings = $this->settings();
        $this->spreadsheetId = (string) ($settings?->spreadsheet_id ?: config('siid.sheets.spreadsheet_id'));
        $this->mainTabs = implode("\n", $settings?->main_tabs ?: config('siid.sheets.main_tabs', []));
        $this->complementaryTabs = implode("\n", $settings?->complementary_tabs ?: config('siid.sheets.complementary_tabs', []));
        $this->refreshStatus($settings);
    }

    public function save(): void
    {
        $this->persist();
        Notification::make()->title('Conexión Google guardada')->success()->send();
    }

    public function testConnection(GoogleSheetsServiceAccountClient $client): void
    {
        $settings = $this->persist();

        try {
            $snapshot = $client->snapshot();
            $settings->update([
                'last_status' => 'conectado',
                'last_error' => null,
                'last_tested_at' => now(),
            ]);
            Notification::make()
                ->title('Conexión verificada')
                ->body(count($snapshot['sheets']).' pestaña(s) principal(es) disponibles.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            $settings->update([
                'last_status' => 'error',
                'last_error' => $exception->getMessage(),
                'last_tested_at' => now(),
            ]);
            Notification::make()->title('La conexión falló')->body($exception->getMessage())->danger()->send();
        }

        $this->refreshStatus($settings->fresh());
    }

    public function synchronize(SincronizadorListadoSiid $synchronizer): void
    {
        try {
            $sync = $synchronizer->execute(true, 'manual', auth()->id());
            Notification::make()
                ->title($sync->estado === 'fallido' ? 'La sincronización falló' : 'Catálogo sincronizado')
                ->body($sync->estado === 'fallido'
                    ? $sync->error_general
                    : "{$sync->creados} nuevos, {$sync->actualizados} actualizados, {$sync->inactivados} inactivados y {$sync->conflictos} conflictos.")
                ->status($sync->estado === 'fallido' ? 'danger' : 'success')
                ->send();
        } catch (Throwable $exception) {
            Notification::make()->title('No fue posible sincronizar')->body($exception->getMessage())->danger()->send();
        }
    }

    private function persist(): SiidGoogleSetting
    {
        $this->validate([
            'spreadsheetId' => ['required', 'string', 'max:180'],
            'mainTabs' => ['required', 'string'],
            'complementaryTabs' => ['nullable', 'string'],
            'credentialsFile' => ['nullable', 'file', 'mimes:json,txt', 'max:1024'],
        ]);

        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('siid_publicacion.google_settings')) {
            throw new RuntimeException('Ejecute las migraciones antes de configurar la conexión Google.');
        }

        $settings = $this->settings() ?? new SiidGoogleSetting;
        $attributes = [
            'spreadsheet_id' => trim($this->spreadsheetId),
            'main_tabs' => $this->lines($this->mainTabs),
            'complementary_tabs' => $this->lines($this->complementaryTabs),
            'updated_by' => auth()->id(),
        ];

        if ($this->credentialsFile instanceof TemporaryUploadedFile) {
            $json = file_get_contents($this->credentialsFile->getRealPath());
            try {
                $credentials = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages(['credentialsFile' => 'El archivo JSON no tiene una estructura válida.']);
            }
            if (blank($credentials['client_email'] ?? null) || blank($credentials['private_key'] ?? null)) {
                throw ValidationException::withMessages(['credentialsFile' => 'El archivo no corresponde a una cuenta de servicio de Google válida.']);
            }
            $attributes += [
                'credentials_encrypted' => Crypt::encryptString((string) $json),
                'credentials_filename' => $this->credentialsFile->getClientOriginalName(),
                'service_account_email' => $credentials['client_email'],
            ];
        }

        $settings->fill($attributes)->save();
        $this->credentialsFile = null;
        $this->credentialsFilename = $settings->credentials_filename;
        $this->serviceAccountEmail = $settings->service_account_email;

        return $settings;
    }

    /** @return list<string> */
    private function lines(string $value): array
    {
        return collect(preg_split('/[\r\n,]+/', $value))
            ->map(fn (?string $line): string => trim((string) $line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function settings(): ?SiidGoogleSetting
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('siid_publicacion.google_settings')) {
            return null;
        }

        return SiidGoogleSetting::query()->first();
    }

    private function refreshStatus(?SiidGoogleSetting $settings): void
    {
        $this->credentialsFilename = $settings?->credentials_filename;
        $this->serviceAccountEmail = $settings?->service_account_email;
        $this->lastStatus = $settings?->last_status;
        $this->lastError = $settings?->last_error;
        $this->lastTestedAt = $settings?->last_tested_at?->format('d/m/Y H:i');
    }
}
