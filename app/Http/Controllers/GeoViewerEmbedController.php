<?php

namespace App\Http\Controllers;

use App\Models\GeoViewer;
use Illuminate\View\View;

class GeoViewerEmbedController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(GeoViewer $geoViewer): View
    {
        abort_unless($geoViewer->isPublished(), 404);

        return view('geo-viewers.embed', [
            'geoViewer' => $geoViewer,
            'configUrl' => route('geo-viewers.config', $geoViewer, absolute: false),
        ]);
    }
}
