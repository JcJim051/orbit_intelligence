<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardPreviewController extends Controller
{
    public function __invoke(Request $request, Dashboard $dashboard): View
    {
        abort_unless($dashboard->canEdit($request->user()) || $request->user()->canApproveDashboards(), 403);

        return view('dashboards.embed', ['dashboard' => $dashboard, 'configUrl' => route('admin.dashboards.preview-config', $dashboard, false), 'preview' => true]);
    }
}
