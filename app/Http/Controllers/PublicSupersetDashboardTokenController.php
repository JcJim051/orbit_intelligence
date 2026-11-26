<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use App\Services\Superset\SupersetClient;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PublicSupersetDashboardTokenController extends Controller
{
    public function __invoke(Dashboard $dashboard, SupersetClient $superset): JsonResponse
    {
        abort_unless(
            $dashboard->isPublished()
            && $dashboard->usesSuperset()
            && filled($dashboard->superset_embedded_uuid),
            404,
        );

        try {
            $token = $superset->tokenInvitado((string) $dashboard->superset_embedded_uuid, [], true);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json([
            'token' => $token,
            'uuid' => $dashboard->superset_embedded_uuid,
            'superset_url' => config('superset.url'),
        ]);
    }
}
