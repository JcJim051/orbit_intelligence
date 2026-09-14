<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Postgis\BootstrapLocalPostgis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class LocalPostgisBootstrapController extends Controller
{
    public function __invoke(BootstrapLocalPostgis $bootstrap, AuditLogger $audit): RedirectResponse
    {
        abort_unless(app()->isLocal() || app()->runningUnitTests(), 404);

        try {
            $credentials = $bootstrap->handle();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No fue posible iniciar PostGIS local. Verifique que Docker Desktop esté abierto e inténtelo de nuevo.');
        }

        $audit->log(null, 'local_postgis_bootstrapped', request()->user(), [
            'host' => $credentials['host'],
            'database' => $credentials['database'],
        ], 'infrastructure');

        return back()
            ->with('status', 'PostGIS local quedó conectado. Copie ahora la credencial de QGIS: se mostrará una sola vez.')
            ->with('local_qgis_credentials', Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR)));
    }
}
