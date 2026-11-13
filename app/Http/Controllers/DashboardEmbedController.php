<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use Illuminate\View\View;

class DashboardEmbedController extends Controller
{
    public function __invoke(Dashboard $dashboard): View
    {
        abort_unless($dashboard->isPublished(), 404);

        if ($dashboard->usesSuperset()) {
            abort_unless(filled($dashboard->superset_embedded_uuid), 404);

            return view('dashboards.superset-embed', ['dashboard' => $dashboard]);
        }

        return view('dashboards.embed', ['dashboard' => $dashboard, 'configUrl' => route('dashboards.config', $dashboard, false), 'preview' => false]);
    }
}
