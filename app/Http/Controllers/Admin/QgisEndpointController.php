<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateQgisEndpointRequest;
use App\Services\AuditLogger;
use App\Services\Postgis\ManagedPostgisConfiguration;
use Illuminate\Http\RedirectResponse;
use Throwable;

class QgisEndpointController extends Controller
{
    public function __invoke(
        UpdateQgisEndpointRequest $request,
        ManagedPostgisConfiguration $configuration,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $configuration->updateQgisEndpoint($validated['qgis_host'], (int) $validated['qgis_port']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'No fue posible actualizar la dirección entregada a QGIS.');
        }

        $audit->log(null, 'qgis_endpoint_updated', $request->user(), [
            'host' => $validated['qgis_host'],
            'port' => (int) $validated['qgis_port'],
        ], 'infrastructure');

        return back()->with('status', 'Dirección para QGIS actualizada. Las credenciales nuevas usarán este servidor.');
    }
}
