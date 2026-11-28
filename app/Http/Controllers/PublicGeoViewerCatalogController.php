<?php

namespace App\Http\Controllers;

use App\Models\GeoViewer;
use App\Models\GeoViewerPublication;
use App\Services\Micrositio\ContenidoMicrositio;
use Illuminate\View\View;

class PublicGeoViewerCatalogController extends Controller
{
    public function index(ContenidoMicrositio $content): View
    {
        $publications = GeoViewerPublication::query()
            ->where('visible', true)
            ->with('viewer')
            ->whereHas('viewer', fn ($query) => $query->where('status', 'published'))
            ->orderBy('orden')->orderBy('id')->get();

        return view('micrositio.geovisores.index', [
            'contenido' => $content->armar(false),
            'publicaciones' => $publications,
        ]);
    }

    public function show(GeoViewer $geoViewer, ContenidoMicrositio $content): View
    {
        $geoViewer->load('publication');
        abort_unless($geoViewer->isPubliclyVisible(), 404);

        return view('micrositio.geovisores.show', [
            'contenido' => $content->armar(false),
            'geoViewer' => $geoViewer,
            'publicacion' => $geoViewer->publication,
        ]);
    }
}
