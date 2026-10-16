<?php

namespace App\Services\InteligenciaGeografica;

use App\Models\InteligenciaGeografica\AnalisisArea;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class MapaBaseAnalisis
{
    private const ATRIBUCION = '© OpenStreetMap contributors. https://www.openstreetmap.org/copyright';

    private const SIN_SATELITE = 'Imagen satelital (Esri World Imagery) no incluida: los términos de Esri no permiten descargar ni almacenar las teselas.';

    /**
     * @param  list<array{color: string, geojson: string|null}>  $cruces
     */
    public function html(AnalisisArea $analisis, array $cruces): string
    {
        $guardada = $this->guardada($analisis);

        if ($guardada !== null) {
            return $this->htmlImagen(
                $guardada['imagen'],
                $guardada['atribucion'],
                $guardada['capturado'],
                $guardada['nota'],
            );
        }

        $anillos = $this->anillos($analisis->geoJson4326());

        if ($anillos === []) {
            return '';
        }

        try {
            $compuesta = $this->componer($anillos, $cruces);
        } catch (Throwable) {
            return $this->aviso('No se pudo componer el mapa del área.');
        }

        if ($compuesta === null) {
            return $this->aviso('No se pudo componer el mapa del área.');
        }

        $capturado = now();
        $this->guardar($analisis, $compuesta['png'], $capturado, $compuesta['nota']);

        return $this->htmlImagen(base64_encode($compuesta['png']), self::ATRIBUCION, $capturado, $compuesta['nota']);
    }

    /**
     * @return array{imagen: string, atribucion: string, capturado: Carbon, nota: string|null}|null
     */
    private function guardada(AnalisisArea $analisis): ?array
    {
        try {
            $fila = DB::selectOne(
                "select encode(mapa_imagen, 'base64') as imagen, mapa_capturado_at, mapa_atribucion, mapa_nota
                 from inteligencia.analisis_area
                 where id = ? and mapa_imagen is not null",
                [$analisis->id],
            );
        } catch (Throwable) {
            return null;
        }

        $imagen = preg_replace('/\s+/', '', (string) ($fila->imagen ?? '')) ?? '';

        if ($imagen === '') {
            return null;
        }

        return [
            'imagen' => $imagen,
            'atribucion' => is_string($fila->mapa_atribucion) && $fila->mapa_atribucion !== ''
                ? $fila->mapa_atribucion
                : self::ATRIBUCION,
            'capturado' => Carbon::parse($fila->mapa_capturado_at ?? now()),
            'nota' => is_string($fila->mapa_nota) && $fila->mapa_nota !== '' ? $fila->mapa_nota : null,
        ];
    }

    private function guardar(AnalisisArea $analisis, string $png, Carbon $capturado, ?string $nota): void
    {
        DB::update(
            "update inteligencia.analisis_area
             set mapa_imagen = decode(?, 'hex'), mapa_capturado_at = ?, mapa_atribucion = ?, mapa_nota = ?
             where id = ?",
            [bin2hex($png), $capturado->utc()->format('Y-m-d H:i:sP'), self::ATRIBUCION, $nota, $analisis->id],
        );
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $anillos
     * @param  list<array{color: string, geojson: string|null}>  $cruces
     * @return array{png: string, nota: string|null}|null
     */
    private function componer(array $anillos, array $cruces): ?array
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $encuadre = $this->encuadre($anillos);
        $lienzo = imagecreatetruecolor($encuadre['ancho'], $encuadre['alto']);

        if ($lienzo === false) {
            return null;
        }

        imagealphablending($lienzo, true);
        $gris = imagecolorallocate($lienzo, 209, 213, 219);
        imagefilledrectangle($lienzo, 0, 0, $encuadre['ancho'], $encuadre['alto'], $gris);
        $fallidas = $this->pegarTeselas($lienzo, $encuadre);

        foreach ($cruces as $cruce) {
            $this->dibujar($lienzo, $this->anillos($cruce['geojson']), $cruce['color'], $encuadre);
        }

        $this->dibujar($lienzo, $anillos, '#047857', $encuadre);

        ob_start();
        $guardo = imagepng($lienzo);
        $png = ob_get_clean();
        imagedestroy($lienzo);

        if ($guardo !== true || ! is_string($png) || $png === '') {
            return null;
        }

        $nota = $fallidas > 0
            ? "No se pudieron descargar {$fallidas} teselas de OpenStreetMap. El polígono se dibujó sobre el fondo disponible."
            : null;

        return ['png' => $png, 'nota' => $nota];
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $anillos
     * @return array{zoom: int, minX: float, minY: float, ancho: int, alto: int, teselas: list<array{x: int, y: int, z: int}>}
     */
    private function encuadre(array $anillos): array
    {
        $minLon = INF;
        $maxLon = -INF;
        $minLat = INF;
        $maxLat = -INF;

        foreach ($anillos as $anillo) {
            foreach ($anillo as $punto) {
                $minLon = min($minLon, $punto[0]);
                $maxLon = max($maxLon, $punto[0]);
                $minLat = min($minLat, $punto[1]);
                $maxLat = max($maxLat, $punto[1]);
            }
        }

        $margen = (float) config('inteligencia.mapa.margen', 0.15);
        $spanLon = max($maxLon - $minLon, 0.002);
        $spanLat = max($maxLat - $minLat, 0.002);
        $centroLon = ($minLon + $maxLon) / 2;
        $centroLat = ($minLat + $maxLat) / 2;
        $minLon = $centroLon - ($spanLon / 2) - ($spanLon * $margen);
        $maxLon = $centroLon + ($spanLon / 2) + ($spanLon * $margen);
        $minLat = max(-85.0, $centroLat - ($spanLat / 2) - ($spanLat * $margen));
        $maxLat = min(85.0, $centroLat + ($spanLat / 2) + ($spanLat * $margen));
        $anchoMax = (int) config('inteligencia.mapa.ancho', 640);
        $altoMax = (int) config('inteligencia.mapa.alto', 420);
        $teselasMax = (int) config('inteligencia.mapa.teselas_max', 12);
        $elegido = null;

        for ($zoom = 17; $zoom >= 0; $zoom--) {
            $minX = $this->pixelX($minLon, $zoom);
            $maxX = $this->pixelX($maxLon, $zoom);
            $minY = $this->pixelY($maxLat, $zoom);
            $maxY = $this->pixelY($minLat, $zoom);
            $ancho = max(1, (int) ceil($maxX - $minX));
            $alto = max(1, (int) ceil($maxY - $minY));
            $teselas = $this->teselas($zoom, $minX, $maxX, $minY, $maxY);
            $elegido = [
                'zoom' => $zoom,
                'minX' => $minX,
                'minY' => $minY,
                'ancho' => $ancho,
                'alto' => $alto,
                'teselas' => $teselas,
            ];

            if ($ancho <= $anchoMax && $alto <= $altoMax && count($teselas) <= $teselasMax) {
                break;
            }
        }

        return $elegido;
    }

    /**
     * @return list<array{x: int, y: int, z: int}>
     */
    private function teselas(int $zoom, float $minX, float $maxX, float $minY, float $maxY): array
    {
        $limite = (2 ** $zoom) - 1;
        $x1 = max(0, min($limite, (int) floor($minX / 256)));
        $x2 = max(0, min($limite, (int) floor(max($minX, $maxX - 0.001) / 256)));
        $y1 = max(0, min($limite, (int) floor($minY / 256)));
        $y2 = max(0, min($limite, (int) floor(max($minY, $maxY - 0.001) / 256)));
        $teselas = [];

        for ($x = $x1; $x <= $x2; $x++) {
            for ($y = $y1; $y <= $y2; $y++) {
                $teselas[] = ['x' => $x, 'y' => $y, 'z' => $zoom];
            }
        }

        return $teselas;
    }

    /**
     * @param  array{zoom: int, minX: float, minY: float, ancho: int, alto: int, teselas: list<array{x: int, y: int, z: int}>}  $encuadre
     */
    private function pegarTeselas(\GdImage $lienzo, array $encuadre): int
    {
        $plantilla = (string) config('inteligencia.mapa.osm_plantilla');
        $agente = (string) config('inteligencia.mapa.agente');
        $fallidas = 0;

        try {
            $respuestas = Http::pool(function (Pool $pool) use ($encuadre, $plantilla, $agente): array {
                $peticiones = [];

                foreach ($encuadre['teselas'] as $tesela) {
                    $url = str_replace(
                        ['{z}', '{x}', '{y}'],
                        [(string) $tesela['z'], (string) $tesela['x'], (string) $tesela['y']],
                        $plantilla,
                    );
                    $peticiones[] = $pool->as($tesela['x'].'-'.$tesela['y'])
                        ->withHeaders(['User-Agent' => $agente])
                        ->connectTimeout(5)
                        ->timeout(8)
                        ->get($url);
                }

                return $peticiones;
            });
        } catch (Throwable) {
            return count($encuadre['teselas']);
        }

        foreach ($encuadre['teselas'] as $tesela) {
            $respuesta = $respuestas[$tesela['x'].'-'.$tesela['y']] ?? null;
            $tile = $this->tesela($respuesta);

            if (! $tile instanceof \GdImage) {
                $fallidas++;

                continue;
            }

            $this->copiar(
                $lienzo,
                $tile,
                (int) round(($tesela['x'] * 256) - $encuadre['minX']),
                (int) round(($tesela['y'] * 256) - $encuadre['minY']),
                $encuadre['ancho'],
                $encuadre['alto'],
            );
            imagedestroy($tile);
        }

        return $fallidas;
    }

    private function tesela(mixed $respuesta): \GdImage|false
    {
        if (! $respuesta instanceof Response || ! $respuesta->successful()) {
            return false;
        }

        try {
            $tile = imagecreatefromstring($respuesta->body());
        } catch (Throwable) {
            return false;
        }

        return $tile instanceof \GdImage ? $tile : false;
    }

    private function copiar(\GdImage $lienzo, \GdImage $tesela, int $destinoX, int $destinoY, int $ancho, int $alto): void
    {
        $origenX = 0;
        $origenY = 0;
        $origenAncho = imagesx($tesela);
        $origenAlto = imagesy($tesela);

        if ($destinoX < 0) {
            $origenX = -$destinoX;
            $origenAncho -= $origenX;
            $destinoX = 0;
        }

        if ($destinoY < 0) {
            $origenY = -$destinoY;
            $origenAlto -= $origenY;
            $destinoY = 0;
        }

        if ($destinoX + $origenAncho > $ancho) {
            $origenAncho = $ancho - $destinoX;
        }

        if ($destinoY + $origenAlto > $alto) {
            $origenAlto = $alto - $destinoY;
        }

        if ($origenAncho < 1 || $origenAlto < 1) {
            return;
        }

        imagecopy($lienzo, $tesela, $destinoX, $destinoY, $origenX, $origenY, $origenAncho, $origenAlto);
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $anillos
     * @param  array{zoom: int, minX: float, minY: float, ancho: int, alto: int}  $encuadre
     */
    private function dibujar(\GdImage $lienzo, array $anillos, string $color, array $encuadre): void
    {
        [$rojo, $verde, $azul] = $this->rgb($color);
        $relleno = imagecolorallocatealpha($lienzo, $rojo, $verde, $azul, 80);
        $borde = imagecolorallocate($lienzo, $rojo, $verde, $azul);

        if ($relleno === false || $borde === false) {
            return;
        }

        imagesetthickness($lienzo, 2);

        foreach ($anillos as $anillo) {
            $puntos = [];

            foreach ($anillo as $punto) {
                $puntos[] = (int) round($this->pixelX($punto[0], $encuadre['zoom']) - $encuadre['minX']);
                $puntos[] = (int) round($this->pixelY($punto[1], $encuadre['zoom']) - $encuadre['minY']);
            }

            if (count($puntos) < 6) {
                continue;
            }

            imagefilledpolygon($lienzo, $puntos, $relleno);
            imagepolygon($lienzo, $puntos, $borde);
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgb(string $color): array
    {
        $hex = ltrim($color, '#');

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [4, 120, 87];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function pixelX(float $lon, int $zoom): float
    {
        return ($lon + 180) / 360 * (2 ** $zoom) * 256;
    }

    private function pixelY(float $lat, int $zoom): float
    {
        $lat = max(-85.05112878, min(85.05112878, $lat));
        $radianes = deg2rad($lat);

        return (1 - log(tan($radianes) + 1 / cos($radianes)) / M_PI) / 2 * (2 ** $zoom) * 256;
    }

    /**
     * @return list<list<array{0: float, 1: float}>>
     */
    private function anillos(?string $geojson): array
    {
        if ($geojson === null || $geojson === '') {
            return [];
        }

        $datos = json_decode($geojson, true);

        return is_array($datos) ? $this->anillosDe($datos) : [];
    }

    /**
     * @param  array<string, mixed>  $geojson
     * @return list<list<array{0: float, 1: float}>>
     */
    private function anillosDe(array $geojson): array
    {
        $tipo = $geojson['type'] ?? null;

        if ($tipo === 'FeatureCollection') {
            $anillos = [];

            foreach ($geojson['features'] ?? [] as $feature) {
                if (is_array($feature)) {
                    array_push($anillos, ...$this->anillosDe($feature));
                }
            }

            return $anillos;
        }

        if ($tipo === 'Feature') {
            return is_array($geojson['geometry'] ?? null) ? $this->anillosDe($geojson['geometry']) : [];
        }

        if ($tipo === 'GeometryCollection') {
            $anillos = [];

            foreach ($geojson['geometries'] ?? [] as $geometria) {
                if (is_array($geometria)) {
                    array_push($anillos, ...$this->anillosDe($geometria));
                }
            }

            return $anillos;
        }

        $coordenadas = $geojson['coordinates'] ?? null;

        if (! is_array($coordenadas)) {
            return [];
        }

        if ($tipo === 'Polygon') {
            return $this->anillo(is_array($coordenadas[0] ?? null) ? $coordenadas[0] : []);
        }

        if ($tipo !== 'MultiPolygon') {
            return [];
        }

        $anillos = [];

        foreach ($coordenadas as $poligono) {
            if (is_array($poligono)) {
                array_push($anillos, ...$this->anillo(is_array($poligono[0] ?? null) ? $poligono[0] : []));
            }
        }

        return $anillos;
    }

    /**
     * @param  list<mixed>  $coordenadas
     * @return list<list<array{0: float, 1: float}>>
     */
    private function anillo(array $coordenadas): array
    {
        $anillo = [];

        foreach ($coordenadas as $punto) {
            if (is_array($punto) && is_numeric($punto[0] ?? null) && is_numeric($punto[1] ?? null)) {
                $anillo[] = [(float) $punto[0], (float) $punto[1]];
            }
        }

        return count($anillo) >= 3 ? [$anillo] : [];
    }

    private function htmlImagen(string $base64, string $atribucion, Carbon $capturado, ?string $nota): string
    {
        $fecha = $capturado->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
        $html = '<img src="data:image/png;base64,'.$base64.'" alt="Mapa del polígono analizado" style="width: 500px; height: auto;">'
            .'<p>Polígono analizado en verde y cruces de las capas sobre el mapa base.</p>'
            .'<p>Mapa base: '.e($atribucion).'. Captura: '.e($fecha).'.</p>'
            .'<p>'.e(self::SIN_SATELITE).'</p>';

        if ($nota !== null) {
            $html .= '<p>'.e($nota).'</p>';
        }

        return $html;
    }

    private function aviso(string $nota): string
    {
        return '<p>'.e($nota).'</p><p>'.e(self::SIN_SATELITE).'</p>';
    }
}
