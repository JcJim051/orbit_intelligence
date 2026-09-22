<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use Illuminate\View\View;

class DashboardEmbedController extends Controller
{
    public function __invoke(Dashboard $dashboard): View
    {
        abort_unless($dashboard->isPublished(), 404);

        return view('dashboards.embed', ['dashboard' => $dashboard, 'configUrl' => route('dashboards.config', $dashboard), 'preview' => false]);
    }
}
