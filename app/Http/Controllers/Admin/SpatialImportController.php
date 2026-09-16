<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SpatialImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpatialImportRequest;
use App\Models\SpatialImport;
use App\Services\AuditLogger;
use App\Services\Postgis\ManagedPostgisConfiguration;
use App\Services\Postgis\ProvisionSpatialImportStaging;
use App\Services\Postgis\SpatialReferenceSystems;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class SpatialImportController extends Controller
{
    public function index(ManagedPostgisConfiguration $configuration): View
    {
        return view('admin.spatial-imports.index', [
            'imports' => SpatialImport::query()->with(['creator', 'dataset'])->latest()->get(),
            'postgis' => $configuration->summary(),
            'credentials' => session('spatial_import_credentials'),
            'storageCrss' => SpatialReferenceSystems::storageOptions(),
        ]);
    }

    public function store(
        StoreSpatialImportRequest $request,
        ProvisionSpatialImportStaging $staging,
        ManagedPostgisConfiguration $configuration,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();
        $token = mb_strtolower(Str::random(16));
        $password = Str::password(40, letters: true, numbers: true, symbols: false, spaces: false);
        $import = SpatialImport::create([
            'name' => $validated['name'],
            'sector' => $validated['sector'],
            'purpose' => $validated['purpose'] ?? null,
            'status' => SpatialImportStatus::Pending,
            'staging_schema' => 'staging_'.$token,
            'database_username' => 'stg_'.$token,
            'database_password' => $password,
            'expires_at' => now()->addHours((int) $validated['valid_for_hours']),
            'created_by' => $request->user()->id,
        ]);

        try {
            $staging->provision($import);
            $import->update(['status' => SpatialImportStatus::StagingReady]);
        } catch (Throwable $exception) {
            report($exception);
            $import->update([
                'status' => SpatialImportStatus::Failed,
                'failure_message' => Str::limit($exception->getMessage(), 2000),
            ]);

            return back()->with('error', 'No fue posible crear la zona temporal: '.$exception->getMessage());
        }

        $connection = $configuration->summary();
        $audit->log(null, 'spatial_import_staging_created', $request->user(), [
            'spatial_import_id' => $import->id,
            'schema' => $import->staging_schema,
        ], 'spatial_import');

        return back()->with('status', 'Zona temporal creada. Copie ahora la credencial para QGIS.')->with('spatial_import_credentials', [
            'host' => $connection['qgis_host'],
            'port' => $connection['qgis_port'],
            'database' => $connection['database'],
            'sslmode' => $connection['sslmode'],
            'username' => $import->database_username,
            'password' => $password,
            'schema' => $import->staging_schema,
            'expires_at' => $import->expires_at->format('d/m/Y H:i'),
        ]);
    }
}
