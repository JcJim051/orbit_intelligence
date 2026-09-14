<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostgisConnectionRequest;
use App\Services\AuditLogger;
use App\Services\Postgis\ManagedPostgisConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class PostgisConnectionController extends Controller
{
    public function index(ManagedPostgisConfiguration $configuration): View
    {
        $credentials = null;
        $encryptedCredentials = session()->pull('local_qgis_credentials');
        if (is_string($encryptedCredentials)) {
            try {
                $decoded = json_decode(Crypt::decryptString($encryptedCredentials), true, 512, JSON_THROW_ON_ERROR);
                $credentials = is_array($decoded) ? $decoded : null;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return view('admin.postgis.index', [
            'postgis' => $configuration->summary(),
            'currentDriver' => DB::getDriverName(),
            'localQgisCredentials' => $credentials,
        ]);
    }

    public function store(
        StorePostgisConnectionRequest $request,
        ManagedPostgisConfiguration $configuration,
        AuditLogger $audit,
    ): RedirectResponse {
        try {
            $configuration->testAndStore($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput($request->safe()->except([
                'admin_password', 'app_password', 'qgis_password', 'reader_password',
            ]))->with('error', 'No fue posible conectar con PostgreSQL. Verifique el servidor, puerto, base, SSL y credenciales.');
        }

        $audit->log(null, 'postgis_connection_configured', $request->user(), [
            'host' => $request->string('host')->toString(),
            'database' => $request->string('database')->toString(),
        ], 'infrastructure');

        return back()->with('status', 'Conexión PostgreSQL verificada y credenciales guardadas de forma cifrada.');
    }
}
