<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeoViewer;
use Illuminate\View\View;

class GeoViewerPreviewController extends Controller
{
    public function __invoke(GeoViewer $geoViewer): View
    {
        return view('geo-viewers.embed', [
            'geoViewer' => $geoViewer,
            'configUrl' => route('admin.geo-viewers.preview-config', $geoViewer, absolute: false),
        ]);
    }
}
