<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Models\GeoViewer;
use App\Models\TabularDataSource;
use App\Services\Dashboards\DashboardRelationshipDiagnostic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class DashboardRelationshipDiagnosticController extends Controller
{
    public function __invoke(Request $request, Dashboard $dashboard, DashboardRelationshipDiagnostic $diagnostic): JsonResponse
    {
        abort_unless($dashboard->canEdit($request->user()), 403);
        $config = $dashboard->draft_config ?? [];
        $source = TabularDataSource::findOrFail($config['data_source_id'] ?? null);
        $viewer = GeoViewer::findOrFail($config['map']['geo_viewer_id'] ?? null);
        try {
            return response()->json($diagnostic->run($source, $viewer, $config['map']['join_data_field'] ?? 'codigo_dane', $config['map']['join_layer_field'] ?? 'codigo_dane'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'El servidor no pudo completar la comprobación territorial. El detalle quedó registrado para diagnóstico.'], 500);
        }
    }
}
