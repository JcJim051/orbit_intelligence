<?php

namespace App\Http\Controllers\Micrositio;

use App\Contracts\Micrositio\FuenteDatosMapaInicio;
use App\Http\Controllers\Controller;
use App\Models\MicrositioMapa;
use App\Services\Micrositio\Mapa\ConfiguracionMapaInicio;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MapaInicioController extends Controller
{
    public function datos(Request $request, FuenteDatosMapaInicio $fuente, ConfiguracionMapaInicio $configuracion): Response
    {
        $armada = $configuracion->armar($this->preview($request));
        $metadatos = $fuente->metadatos();
        $documento = [
            'version' => $metadatos['version'],
            'departamento' => $metadatos['departamento'],
            'configuracion' => [
                'capa_principal' => $armada['capa_principal'],
                'paleta' => $armada['paleta'],
                'polaridad' => $armada['polaridad'],
                'periodo_modo' => $armada['periodo_modo'],
                'periodo_fijo' => $armada['periodo_fijo'],
                'texto_tarjeta' => $armada['texto_tarjeta'],
                'texto_cta' => $armada['texto_cta'],
                'url_cta' => $armada['url_cta'],
                'url_ficha_tecnica' => $armada['url_ficha_tecnica'],
                'url_indicadores_municipio' => $armada['url_indicadores_municipio'],
            ],
            'capas' => $armada['capas'],
        ];

        return $this->json($request, $documento);
    }

    public function municipios(Request $request, FuenteDatosMapaInicio $fuente): Response
    {
        return $this->json($request, $fuente->geometriaMunicipios());
    }

    public function colombia(Request $request, FuenteDatosMapaInicio $fuente): Response
    {
        return $this->json($request, $fuente->geometriaColombia());
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function json(Request $request, array $documento): Response
    {
        $cuerpo = json_encode($documento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $etag = '"'.sha1((string) $cuerpo).'"';
        $cache = $this->preview($request)
            ? 'private, no-store'
            : 'public, max-age=86400, stale-while-revalidate=604800';

        if (! $this->preview($request) && trim((string) $request->headers->get('If-None-Match'), '"') === trim($etag, '"')) {
            return response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => $cache,
            ]);
        }

        return response($cuerpo, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'ETag' => $etag,
            'Cache-Control' => $cache,
        ]);
    }

    private function preview(Request $request): bool
    {
        return $request->boolean('preview') && ($request->user()?->can('update', MicrositioMapa::class) ?? false);
    }
}
