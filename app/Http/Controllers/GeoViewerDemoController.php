<?php

namespace App\Http\Controllers;

use App\Enums\GeoViewerStatus;
use App\Models\GeoViewer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeoViewerDemoController extends Controller
{
    public function __invoke(Request $request): View
    {
        $viewers = GeoViewer::query()
            ->where('status', GeoViewerStatus::Published->value)
            ->withCount(['layers' => fn ($query) => $query->where('active', true)])
            ->orderBy('name')
            ->get();

        $selectedViewer = $viewers->firstWhere('slug', $request->string('visor')->toString())
            ?? $viewers->first();

        return view('geo-viewers.demo', [
            'viewers' => $viewers,
            'selectedViewer' => $selectedViewer,
        ]);
    }
}
