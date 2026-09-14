<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Postgis\ManagedPostgisConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class QgisPostgisCredentialController extends Controller
{
    public function __invoke(ManagedPostgisConfiguration $configuration, AuditLogger $audit): RedirectResponse
    {
        try {
            $credentials = $configuration->rotateQgisPassword();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No fue posible regenerar la credencial QGIS. Verifique que PostgreSQL esté disponible.');
        }

        $audit->log(null, 'qgis_postgis_credential_rotated', request()->user(), [
            'host' => $credentials['host'],
            'database' => $credentials['database'],
            'username' => $credentials['username'],
        ], 'infrastructure');

        return back()
            ->with('status', 'La credencial QGIS fue regenerada. Cópiela ahora: se mostrará una sola vez.')
            ->with('local_qgis_credentials', Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR)));
    }
}
