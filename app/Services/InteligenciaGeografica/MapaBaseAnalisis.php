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

    private const CONTEXTO = 'Se agregó un recuadro de contexto con la ubicación en el municipio.';

    /** @var array<int, \GdImage|null> */
    private array $recuadros = [];

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
            $compuesta = $this->componer($analisis, $anillos, $cruces);
        } catch (Throwable) {
            return $this->aviso('No se pudo componer el mapa del área.');
        }

        if ($compuesta === null) {
            return $this->aviso('No se pudo componer el mapa del área.');
        }

        $capturado = now();
        $this->guardar($analisis, $compuesta['png'], $compuesta['base'], $capturado, $compuesta['nota']);

        return $this->htmlImagen(base64_encode($compuesta['png']), self::ATRIBUCION, $capturado, $compuesta['nota']);
    }

    public function olvidar(AnalisisArea $analisis): void
    {
        unset($this->recuadros[$analisis->id]);

        DB::update(
            'update inteligencia.analisis_area
             set mapa_imagen = null, mapa_base_imagen = null, mapa_capturado_at = null,
                 mapa_atribucion = null, mapa_nota = null
             where id = ?',
            [$analisis->id],
        );
        DB::delete(
            'delete from inteligencia.analisis_mapa_capa where analisis_area_id = ?',
            [$analisis->id],
        );
    }

    /**
     * Mapa de una sola capa: el polígono analizado y la intersección, sobre las teselas ya guardadas.
     *
     * @param  list<array{tipo: string, color: string, geojson: string|null}>  $grupos
     */
    public function htmlCapa(AnalisisArea $analisis, int $capaId, array $grupos): string
    {
        if ($grupos === []) {
            return '';
        }

        $guardada = $this->capaGuardada($analisis, $capaId);

        if ($guardada !== null) {
            return $this->htmlImagen(
                $guardada['imagen'],
                $guardada['atribucion'],
                $guardada['capturado'],
                $guardada['nota'],
                $guardada['leyenda'],
            );
        }

        $anillos = $this->anillos($analisis->geoJson4326());

        if ($anillos === []) {
            return '';
        }

        $encuadre = $this->encuadre($anillos);
        $base = $this->baseGuardada($analisis, $encuadre);
        $lienzo = null;

        try {
            if ($base === null) {
                $preparado = $this->prepararLienzo($anillos);

                if ($preparado === null) {
                    return $this->aviso('No se pudo componer el mapa de la capa.');
                }

                $this->guardarBase($analisis, $preparado['base'], $preparado['nota']);
                $lienzo = $preparado['lienzo'];
                $nota = $preparado['nota'];
            } else {
                $creado = imagecreatefromstring($base['png']);

                if ($creado === false) {
                    return $this->aviso('No se pudo componer el mapa de la capa.');
                }

                $lienzo = $creado;
                $nota = $base['nota'];
            }

            if ($encuadre['contexto'] && ! str_contains((string) $nota, self::CONTEXTO)) {
                $nota = trim(((string) $nota).' '.self::CONTEXTO);
            }

            imagealphablending($lienzo, true);

            foreach ($grupos as $grupo) {
                $this->dibujar($lienzo, $this->anillos($grupo['geojson']), $grupo['color'], $encuadre);
            }

            $this->dibujar($lienzo, $anillos, '#047857', $encuadre);
            $this->cartografia($lienzo, $encuadre, $analisis);
            $png = $this->pngDe($lienzo);
        } catch (Throwable) {
            $png = null;
        } finally {
            if ($lienzo instanceof \GdImage) {
                imagedestroy($lienzo);
                $lienzo = null;
            }
        }

        if ($png === null) {
            return $this->aviso('No se pudo componer el mapa de la capa.');
        }

        $leyenda = array_map(fn (array $grupo): array => [
            'tipo' => $grupo['tipo'],
            'color' => $grupo['color'],
        ], $grupos);
        $capturado = now();
        $this->guardarCapa($analisis, $capaId, $png, $capturado, $nota, $leyenda);

        return $this->htmlImagen(base64_encode($png), self::ATRIBUCION, $capturado, $nota, $leyenda);
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

    private function guardar(AnalisisArea $analisis, string $png, string $base, Carbon $capturado, ?string $nota): void
    {
        DB::update(
            "update inteligencia.analisis_area
             set mapa_imagen = decode(?, 'hex'), mapa_base_imagen = decode(?, 'hex'),
                 mapa_capturado_at = ?, mapa_atribucion = ?, mapa_nota = ?
             where id = ?",
            [bin2hex($png), bin2hex($base), $capturado->utc()->format('Y-m-d H:i:sP'), self::ATRIBUCION, $nota, $analisis->id],
        );
    }

    /**
     * @param  array{zoom: int, ancho: int, alto: int}  $encuadre
     * @return array{png: string, nota: string|null}|null
     */
    private function baseGuardada(AnalisisArea $analisis, array $encuadre): ?array
    {
        try {
            $fila = DB::selectOne(
                "select encode(mapa_base_imagen, 'base64') as imagen, mapa_nota
                 from inteligencia.analisis_area
                 where id = ? and mapa_base_imagen is not null",
                [$analisis->id],
            );
        } catch (Throwable) {
            return null;
        }

        $png = base64_decode(preg_replace('/\s+/', '', (string) ($fila->imagen ?? '')) ?? '', true);

        if (! is_string($png) || $png === '' || ! $this->cabe($png, $encuadre)) {
            return null;
        }

        $nota = $fila->mapa_nota ?? null;

        return [
            'png' => $png,
            'nota' => is_string($nota) && $nota !== '' ? $nota : null,
        ];
    }

    private function guardarBase(AnalisisArea $analisis, string $png, ?string $nota): void
    {
        DB::update(
            "update inteligencia.analisis_area
             set mapa_base_imagen = decode(?, 'hex'), mapa_nota = coalesce(mapa_nota, ?)
             where id = ?",
            [bin2hex($png), $nota, $analisis->id],
        );
    }

    /**
     * @return array{imagen: string, atribucion: string, capturado: Carbon, nota: string|null, leyenda: list<array{tipo: string, color: string}>}|null
     */
    private function capaGuardada(AnalisisArea $analisis, int $capaId): ?array
    {
        try {
            $fila = DB::selectOne(
                "select encode(imagen, 'base64') as imagen, capturado_at, atribucion, nota, leyenda::text as leyenda
                 from inteligencia.analisis_mapa_capa
                 where analisis_area_id = ? and capa_id = ?",
                [$analisis->id, $capaId],
            );
        } catch (Throwable) {
            return null;
        }

        $imagen = preg_replace('/\s+/', '', (string) ($fila->imagen ?? '')) ?? '';

        if ($imagen === '') {
            return null;
        }

        $leyenda = json_decode((string) ($fila->leyenda ?? '[]'), true);

        return [
            'imagen' => $imagen,
            'atribucion' => is_string($fila->atribucion) && $fila->atribucion !== '' ? $fila->atribucion : self::ATRIBUCION,
            'capturado' => Carbon::parse($fila->capturado_at ?? now()),
            'nota' => is_string($fila->nota) && $fila->nota !== '' ? $fila->nota : null,
            'leyenda' => is_array($leyenda) ? $leyenda : [],
        ];
    }

    /**
     * @param  list<array{tipo: string, color: string}>  $leyenda
     */
    private function guardarCapa(AnalisisArea $analisis, int $capaId, string $png, Carbon $capturado, ?string $nota, array $leyenda): void
    {
        DB::insert(
            "insert into inteligencia.analisis_mapa_capa
                (analisis_area_id, capa_id, imagen, capturado_at, atribucion, nota, leyenda)
             values (?, ?, decode(?, 'hex'), ?, ?, ?, ?::jsonb)
             on conflict (analisis_area_id, capa_id) do update
                set imagen = excluded.imagen, capturado_at = excluded.capturado_at,
                    atribucion = excluded.atribucion, nota = excluded.nota, leyenda = excluded.leyenda",
            [
                $analisis->id,
                $capaId,
                bin2hex($png),
                $capturado->utc()->format('Y-m-d H:i:sP'),
                self::ATRIBUCION,
                $nota,
                json_encode($leyenda, JSON_UNESCAPED_UNICODE) ?: '[]',
            ],
        );
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $anillos
     * @param  list<array{color: string, geojson: string|null}>  $cruces
     * @return array{png: string, nota: string|null}|null
     */
    /**
     * @param  list<list<array{0: float, 1: float}>>  $anillos
     * @return array{lienzo: \GdImage, base: string, nota: string|null}|null
     */
    private function prepararLienzo(array $anillos): ?array
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
        $base = $this->pngDe($lienzo);

        if ($base === null) {
            imagedestroy($lienzo);

            return null;
        }

        $nota = $fallidas > 0
            ? "No se pudieron descargar {$fallidas} teselas de OpenStreetMap. El polígono se dibujó sobre el fondo disponible."
            : null;

        return ['lienzo' => $lienzo, 'base' => $base, 'nota' => $nota];
    }

    private function pngDe(\GdImage $lienzo): ?string
    {
        $ancho = imagesx($lienzo);
        $alto = imagesy($lienzo);
        $plano = imagecreatetruecolor($ancho, $alto);

        if ($plano === false) {
            return null;
        }

        imagealphablending($plano, true);
        $blanco = imagecolorallocate($plano, 255, 255, 255);
        imagefilledrectangle($plano, 0, 0, $ancho, $alto, $blanco);
        imagecopy($plano, $lienzo, 0, 0, 0, 0, $ancho, $alto);
        imageinterlace($plano, false);
        imagesavealpha($plano, false);
        ob_start();
        $guardo = imagepng($plano, null, 6);
        $png = ob_get_clean();
        imagedestroy($plano);

        return $guardo === true && is_string($png) && $png !== '' ? $png : null;
    }

    /**
     * @param  array{ancho: int, alto: int}  $encuadre
     */
    private function cabe(string $png, array $encuadre): bool
    {
        $imagen = imagecreatefromstring($png);

        if ($imagen === false) {
            return false;
        }

        $cabe = imagesx($imagen) === $encuadre['ancho'] && imagesy($imagen) === $encuadre['alto'];
        imagedestroy($imagen);

        return $cabe;
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $anillos
     * @param  list<array{color: string, geojson: string|null}>  $cruces
     * @return array{png: string, base: string, nota: string|null}|null
     */
    private function componer(AnalisisArea $analisis, array $anillos, array $cruces): ?array
    {
        $preparado = $this->prepararLienzo($anillos);

        if ($preparado === null) {
            return null;
        }

        $encuadre = $this->encuadre($anillos);
        $lienzo = $preparado['lienzo'];
        $nota = $preparado['nota'];

        if ($encuadre['contexto']) {
            $nota = trim(((string) $nota).' '.self::CONTEXTO);
        }

        foreach ($cruces as $cruce) {
            $this->dibujar($lienzo, $this->anillos($cruce['geojson']), $cruce['color'], $encuadre);
        }

        $this->dibujar($lienzo, $anillos, '#047857', $encuadre);
        $this->cartografia($lienzo, $encuadre, $analisis);
        $png = $this->pngDe($lienzo);
        imagedestroy($lienzo);

        if ($png === null) {
            return null;
        }

        return ['png' => $png, 'base' => $preparado['base'], 'nota' => $nota === '' ? null : $nota];
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $anillos
     * @return array{
     *     zoom: int, minX: float, minY: float, ancho: int, alto: int,
     *     teselas: list<array{x: int, y: int, z: int}>, centroLat: float, contexto: bool,
     *     poligono: array{minLon: float, maxLon: float, minLat: float, maxLat: float}
     * }
     */
    private function encuadre(array $anillos): array
    {
        $poligono = $this->limites($anillos);
        $margen = max(0.0, (float) config('inteligencia.mapa.margen', 0.2));
        $spanLon = max($poligono['maxLon'] - $poligono['minLon'], 1e-8);
        $spanLat = max($poligono['maxLat'] - $poligono['minLat'], 1e-8);
        $centroLon = ($poligono['minLon'] + $poligono['maxLon']) / 2;
        $centroLat = ($poligono['minLat'] + $poligono['maxLat']) / 2;
        $vista = $this->vista(
            $centroLon,
            $centroLat,
            ($spanLon / 2) * (1 + (2 * $margen)),
            ($spanLat / 2) * (1 + (2 * $margen)),
        );
        $zoomMax = min(19, max(0, (int) config('inteligencia.mapa.zoom_max', 19)));
        $anchoMax = (int) config('inteligencia.mapa.ancho_max', 1680);
        $altoMax = (int) config('inteligencia.mapa.alto_max', 1680);
        $teselasMax = (int) config('inteligencia.mapa.teselas_max', 48);
        $zoom = 0;

        for ($candidato = $zoomMax; $candidato >= 0; $candidato--) {
            $medida = $this->medida($vista, $candidato);

            if ($medida['ancho'] <= $anchoMax && $medida['alto'] <= $altoMax && count($medida['teselas']) <= $teselasMax) {
                $zoom = $candidato;

                break;
            }
        }

        if ($zoom === $zoomMax) {
            $vista = $this->ampliar($vista, $zoom, $this->anchoPagina() * $this->escala(), $anchoMax, $altoMax, $teselasMax);
        }

        $medida = $this->medida($vista, $zoom);
        $anchoPoligono = $this->pixelX($poligono['maxLon'], $zoom) - $this->pixelX($poligono['minLon'], $zoom);

        return [
            'zoom' => $zoom,
            'minX' => $medida['minX'],
            'minY' => $medida['minY'],
            'ancho' => $medida['ancho'],
            'alto' => $medida['alto'],
            'teselas' => $medida['teselas'],
            'centroLat' => $centroLat,
            'contexto' => $anchoPoligono < ($medida['ancho'] * 0.3),
            'poligono' => $poligono,
        ];
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $anillos
     * @return array{minLon: float, maxLon: float, minLat: float, maxLat: float}
     */
    private function limites(array $anillos): array
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

        return ['minLon' => $minLon, 'maxLon' => $maxLon, 'minLat' => $minLat, 'maxLat' => $maxLat];
    }

    /**
     * @return array{minLon: float, maxLon: float, minLat: float, maxLat: float}
     */
    private function vista(float $centroLon, float $centroLat, float $medioLon, float $medioLat): array
    {
        return [
            'minLon' => $centroLon - $medioLon,
            'maxLon' => $centroLon + $medioLon,
            'minLat' => max(-85.0, $centroLat - $medioLat),
            'maxLat' => min(85.0, $centroLat + $medioLat),
        ];
    }

    /**
     * @param  array{minLon: float, maxLon: float, minLat: float, maxLat: float}  $vista
     * @return array{minLon: float, maxLon: float, minLat: float, maxLat: float}
     */
    private function ampliar(array $vista, int $zoom, int $objetivo, int $anchoMax, int $altoMax, int $teselasMax): array
    {
        $actual = $this->medida($vista, $zoom);

        if ($actual['ancho'] >= $objetivo) {
            return $vista;
        }

        $centroLon = ($vista['minLon'] + $vista['maxLon']) / 2;
        $centroLat = ($vista['minLat'] + $vista['maxLat']) / 2;
        $medioLon = max(($vista['maxLon'] - $vista['minLon']) / 2, 1e-8);
        $medioLat = max(($vista['maxLat'] - $vista['minLat']) / 2, 1e-8);
        $mejor = $vista;
        $bajo = 1.0;
        $alto = 40.0;

        for ($paso = 0; $paso < 16; $paso++) {
            $factor = ($bajo + $alto) / 2;
            $candidata = $this->vista($centroLon, $centroLat, $medioLon * $factor, $medioLat * $factor);
            $medida = $this->medida($candidata, $zoom);
            $cabe = $medida['ancho'] <= $anchoMax && $medida['alto'] <= $altoMax && count($medida['teselas']) <= $teselasMax;

            if (! $cabe) {
                $alto = $factor;

                continue;
            }

            if ($medida['ancho'] >= $objetivo) {
                $mejor = $candidata;
                $alto = $factor;

                continue;
            }

            $bajo = $factor;
            $alcanzado = $this->medida($mejor, $zoom)['ancho'] >= $objetivo;

            if (! $alcanzado && $medida['ancho'] > $this->medida($mejor, $zoom)['ancho']) {
                $mejor = $candidata;
            }
        }

        return $mejor;
    }

    /**
     * @param  array{minLon: float, maxLon: float, minLat: float, maxLat: float}  $vista
     * @return array{minX: float, minY: float, ancho: int, alto: int, teselas: list<array{x: int, y: int, z: int}>}
     */
    private function medida(array $vista, int $zoom): array
    {
        $minX = (int) floor($this->pixelX($vista['minLon'], $zoom));
        $maxX = (int) ceil($this->pixelX($vista['maxLon'], $zoom));
        $minY = (int) floor($this->pixelY($vista['maxLat'], $zoom));
        $maxY = (int) ceil($this->pixelY($vista['minLat'], $zoom));
        $ancho = max(1, $maxX - $minX);
        $alto = max(1, $maxY - $minY);

        return [
            'minX' => (float) $minX,
            'minY' => (float) $minY,
            'ancho' => $ancho,
            'alto' => $alto,
            'teselas' => $this->teselas($zoom, $minX, $maxX, $minY, $maxY),
        ];
    }

    /**
     * @return list<array{x: int, y: int, z: int}>
     */
    private function teselas(int $zoom, int $minX, int $maxX, int $minY, int $maxY): array
    {
        $limite = (2 ** $zoom) - 1;
        $x1 = max(0, min($limite, intdiv($minX, 256)));
        $x2 = max(0, min($limite, intdiv(max($minX, $maxX - 1), 256)));
        $y1 = max(0, min($limite, intdiv($minY, 256)));
        $y2 = max(0, min($limite, intdiv(max($minY, $maxY - 1), 256)));
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
        $fallidas = 0;

        foreach (array_chunk($encuadre['teselas'], 8) as $grupo) {
            $fallidas += $this->pegarGrupo($lienzo, $encuadre, $grupo);
        }

        return $fallidas;
    }

    /**
     * @param  array{zoom: int, minX: float, minY: float, ancho: int, alto: int, teselas: list<array{x: int, y: int, z: int}>}  $encuadre
     * @param  list<array{x: int, y: int, z: int}>  $grupo
     */
    private function pegarGrupo(\GdImage $lienzo, array $encuadre, array $grupo): int
    {
        $plantilla = (string) config('inteligencia.mapa.osm_plantilla');
        $agente = (string) config('inteligencia.mapa.agente');

        try {
            $respuestas = Http::pool(function (Pool $pool) use ($grupo, $plantilla, $agente): array {
                $peticiones = [];

                foreach ($grupo as $tesela) {
                    $url = str_replace(
                        ['{z}', '{x}', '{y}'],
                        [(string) $tesela['z'], (string) $tesela['x'], (string) $tesela['y']],
                        $plantilla,
                    );
                    $peticiones[] = $pool->as($tesela['z'].'-'.$tesela['x'].'-'.$tesela['y'])
                        ->withHeaders(['User-Agent' => $agente])
                        ->connectTimeout(5)
                        ->timeout(8)
                        ->get($url);
                }

                return $peticiones;
            });
        } catch (Throwable) {
            return count($grupo);
        }

        $fallidas = 0;

        foreach ($grupo as $tesela) {
            $respuesta = $respuestas[$tesela['z'].'-'.$tesela['x'].'-'.$tesela['y']] ?? null;
            $tile = $this->tesela($respuesta);

            if (! $tile instanceof \GdImage || imagesx($tile) !== 256 || imagesy($tile) !== 256) {
                if ($tile instanceof \GdImage) {
                    imagedestroy($tile);
                }

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
        $relleno = imagecolorallocatealpha($lienzo, $rojo, $verde, $azul, 72);
        $borde = imagecolorallocate($lienzo, $rojo, $verde, $azul);
        $casco = imagecolorallocate($lienzo, 255, 255, 255);

        if ($relleno === false || $borde === false || $casco === false) {
            return;
        }

        if (function_exists('imageantialias')) {
            imageantialias($lienzo, true);
        }

        foreach ($anillos as $anillo) {
            $puntos = [];

            foreach ($anillo as $punto) {
                $puntos[] = (int) round($this->pixelX($punto[0], $encuadre['zoom']) - $encuadre['minX']);
                $puntos[] = (int) round($this->pixelY($punto[1], $encuadre['zoom']) - $encuadre['minY']);
            }

            if (count($puntos) < 4) {
                continue;
            }

            if ($this->cerrado($anillo)) {
                imagefilledpolygon($lienzo, $puntos, $relleno);
                imagesetthickness($lienzo, 8);
                imagepolygon($lienzo, $puntos, $casco);
                imagesetthickness($lienzo, 4);
                imagepolygon($lienzo, $puntos, $borde);

                continue;
            }

            imagesetthickness($lienzo, 6);
            $this->trazo($lienzo, $puntos, $casco);
            imagesetthickness($lienzo, 3);
            $this->trazo($lienzo, $puntos, $borde);
        }

        imagesetthickness($lienzo, 1);
    }

    /**
     * @param  list<int>  $puntos
     */
    private function trazo(\GdImage $lienzo, array $puntos, int $color): void
    {
        for ($indice = 2; $indice < count($puntos); $indice += 2) {
            imageline($lienzo, $puntos[$indice - 2], $puntos[$indice - 1], $puntos[$indice], $puntos[$indice + 1], $color);
        }
    }

    /**
     * @param  list<array{0: float, 1: float}>  $anillo
     */
    private function cerrado(array $anillo): bool
    {
        if (count($anillo) < 4) {
            return false;
        }

        $inicio = $anillo[0];
        $fin = $anillo[array_key_last($anillo)];

        return abs($inicio[0] - $fin[0]) < 1e-8 && abs($inicio[1] - $fin[1]) < 1e-8;
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

        if ($tipo === 'LineString') {
            return $this->anillo($coordenadas);
        }

        if ($tipo === 'MultiLineString') {
            $anillos = [];

            foreach ($coordenadas as $linea) {
                if (is_array($linea)) {
                    array_push($anillos, ...$this->anillo($linea));
                }
            }

            return $anillos;
        }

        if ($tipo === 'Point' && is_numeric($coordenadas[0] ?? null) && is_numeric($coordenadas[1] ?? null)) {
            $lon = (float) $coordenadas[0];
            $lat = (float) $coordenadas[1];

            return $this->anillo([
                [$lon, $lat],
                [$lon + 0.001, $lat],
                [$lon + 0.001, $lat + 0.001],
                [$lon, $lat + 0.001],
                [$lon, $lat],
            ]);
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

        return count($anillo) >= 2 ? [$anillo] : [];
    }

    /**
     * @param  array{zoom: int, minX: float, minY: float, ancho: int, alto: int, centroLat: float, contexto: bool, poligono: array{minLon: float, maxLon: float, minLat: float, maxLat: float}}  $encuadre
     */
    private function cartografia(\GdImage $lienzo, array $encuadre, AnalisisArea $analisis): void
    {
        $this->escalaGrafica($lienzo, $encuadre);
        $this->norte($lienzo, $encuadre);
        $this->recuadro($lienzo, $encuadre, $analisis);
        $this->atribucion($lienzo);
    }

    /**
     * @param  array{zoom: int, ancho: int, alto: int, centroLat: float}  $encuadre
     */
    private function escalaGrafica(\GdImage $lienzo, array $encuadre): void
    {
        $metrosPorPixel = 156543.03392 * cos(deg2rad($encuadre['centroLat'])) / (2 ** $encuadre['zoom']);

        if ($metrosPorPixel <= 0) {
            return;
        }

        $metros = $this->distanciaLegible($metrosPorPixel * 180);
        $pixeles = (int) round($metros / $metrosPorPixel);
        $x = max(24, $encuadre['ancho'] - $pixeles - 24);
        $y = $encuadre['alto'] - 78;
        $negro = imagecolorallocate($lienzo, 15, 23, 42);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);

        if ($negro === false || $blanco === false || $y < 20) {
            return;
        }

        imagefilledrectangle($lienzo, $x - 8, $y - 28, $x + $pixeles + 8, $y + 16, $blanco);
        imagesetthickness($lienzo, 4);
        imageline($lienzo, $x, $y, $x + $pixeles, $y, $negro);
        imageline($lienzo, $x, $y - 8, $x, $y + 8, $negro);
        imageline($lienzo, $x + $pixeles, $y - 8, $x + $pixeles, $y + 8, $negro);
        imagesetthickness($lienzo, 1);
        $texto = $metros >= 1000 ? ($metros / 1000).' km' : $metros.' m';
        $this->texto($lienzo, $texto, $x, $y - 8, 18, $negro);
    }

    private function distanciaLegible(float $metros): int
    {
        $pasos = [10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000];
        $elegido = 10;

        foreach ($pasos as $paso) {
            if ($paso <= $metros) {
                $elegido = $paso;
            }
        }

        return $elegido;
    }

    /**
     * @param  array{ancho: int}  $encuadre
     */
    private function norte(\GdImage $lienzo, array $encuadre): void
    {
        $x = $encuadre['ancho'] - 64;
        $y = 28;
        $negro = imagecolorallocate($lienzo, 15, 23, 42);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);

        if ($negro === false || $blanco === false || $x < 20) {
            return;
        }

        imagefilledellipse($lienzo, $x + 16, $y + 22, 52, 52, $blanco);
        imagefilledpolygon($lienzo, [$x + 16, $y, $x + 28, $y + 28, $x + 16, $y + 22, $x + 4, $y + 28], $negro);
        $this->texto($lienzo, 'N', $x + 8, $y + 46, 18, $negro);
    }

    private function atribucion(\GdImage $lienzo): void
    {
        $ancho = imagesx($lienzo);
        $alto = imagesy($lienzo);
        $negro = imagecolorallocate($lienzo, 15, 23, 42);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);

        if ($negro === false || $blanco === false) {
            return;
        }

        imagefilledrectangle($lienzo, 0, $alto - 36, $ancho, $alto, $blanco);
        $this->texto($lienzo, '© OpenStreetMap contributors', 16, $alto - 8, 16, $negro);
    }

    /**
     * @param  array{zoom: int, ancho: int, alto: int, contexto: bool, poligono: array{minLon: float, maxLon: float, minLat: float, maxLat: float}}  $encuadre
     */
    private function recuadro(\GdImage $lienzo, array $encuadre, AnalisisArea $analisis): void
    {
        if (! $encuadre['contexto']) {
            return;
        }

        $recorte = $this->recorteContexto($analisis, $encuadre);

        if ($recorte === null) {
            return;
        }

        $ancho = imagesx($recorte);
        $alto = imagesy($recorte);
        $x = 16;
        $y = imagesy($lienzo) - 36 - $alto - 16;

        if ($y < 16) {
            imagedestroy($recorte);

            return;
        }

        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        $negro = imagecolorallocate($lienzo, 15, 23, 42);

        if ($blanco !== false) {
            imagefilledrectangle($lienzo, $x - 4, $y - 4, $x + $ancho + 4, $y + $alto + 4, $blanco);
        }

        imagecopy($lienzo, $recorte, $x, $y, 0, 0, $ancho, $alto);
        imagedestroy($recorte);

        if ($negro !== false) {
            imagerectangle($lienzo, $x - 4, $y - 4, $x + $ancho + 4, $y + $alto + 4, $negro);
        }
    }

    /**
     * @param  array{zoom: int, poligono: array{minLon: float, maxLon: float, minLat: float, maxLat: float}}  $encuadre
     */
    private function recorteContexto(AnalisisArea $analisis, array $encuadre): ?\GdImage
    {
        if (array_key_exists($analisis->id, $this->recuadros)) {
            $guardado = $this->recuadros[$analisis->id];

            return $guardado instanceof \GdImage ? $this->clonar($guardado) : null;
        }

        $municipio = $this->municipio($analisis);
        $limites = $municipio['limites'] ?? $this->alrededor($encuadre['poligono']);
        $zoomTope = max(0, $encuadre['zoom'] - 3);
        $zoom = 0;
        $medida = null;

        for ($candidato = $zoomTope; $candidato >= 0; $candidato--) {
            $propuesta = $this->medida($limites, $candidato);

            if ($propuesta['ancho'] <= 320 && $propuesta['alto'] <= 240 && count($propuesta['teselas']) <= 12) {
                $zoom = $candidato;
                $medida = $propuesta;

                break;
            }
        }

        if ($medida === null) {
            $this->recuadros[$analisis->id] = null;

            return null;
        }

        $lienzo = imagecreatetruecolor($medida['ancho'], $medida['alto']);

        if ($lienzo === false) {
            $this->recuadros[$analisis->id] = null;

            return null;
        }

        imagealphablending($lienzo, true);
        $gris = imagecolorallocate($lienzo, 241, 245, 249);
        imagefilledrectangle($lienzo, 0, 0, $medida['ancho'], $medida['alto'], $gris);
        $this->pegarTeselas($lienzo, [
            'zoom' => $zoom,
            'minX' => $medida['minX'],
            'minY' => $medida['minY'],
            'ancho' => $medida['ancho'],
            'alto' => $medida['alto'],
            'teselas' => $medida['teselas'],
        ]);

        if ($municipio !== null) {
            $this->dibujar($lienzo, $municipio['anillos'], '#1d4ed8', [
                'zoom' => $zoom,
                'minX' => $medida['minX'],
                'minY' => $medida['minY'],
            ]);
        }

        $this->marca($lienzo, $encuadre['poligono'], $zoom, $medida['minX'], $medida['minY']);
        $this->texto($lienzo, 'Ubicación', 8, 22, 14, imagecolorallocate($lienzo, 15, 23, 42) ?: 0);
        $this->recuadros[$analisis->id] = $lienzo;

        return $this->clonar($lienzo);
    }

    private function clonar(\GdImage $origen): \GdImage
    {
        $copia = imagecreatetruecolor(imagesx($origen), imagesy($origen));
        imagecopy($copia, $origen, 0, 0, 0, 0, imagesx($origen), imagesy($origen));

        return $copia;
    }

    /**
     * @param  array{minLon: float, maxLon: float, minLat: float, maxLat: float}  $poligono
     */
    private function marca(\GdImage $lienzo, array $poligono, int $zoom, float $minX, float $minY): void
    {
        $x = (int) round($this->pixelX(($poligono['minLon'] + $poligono['maxLon']) / 2, $zoom) - $minX);
        $y = (int) round($this->pixelY(($poligono['minLat'] + $poligono['maxLat']) / 2, $zoom) - $minY);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        $verde = imagecolorallocate($lienzo, 4, 120, 87);

        if ($blanco === false || $verde === false) {
            return;
        }

        imagefilledellipse($lienzo, $x, $y, 16, 16, $blanco);
        imagefilledellipse($lienzo, $x, $y, 8, 8, $verde);
    }

    /**
     * @param  array{minLon: float, maxLon: float, minLat: float, maxLat: float}  $poligono
     * @return array{minLon: float, maxLon: float, minLat: float, maxLat: float}
     */
    private function alrededor(array $poligono): array
    {
        $centroLon = ($poligono['minLon'] + $poligono['maxLon']) / 2;
        $centroLat = ($poligono['minLat'] + $poligono['maxLat']) / 2;

        return $this->vista($centroLon, $centroLat, 0.04, 0.04);
    }

    /**
     * @return array{nombre: string, anillos: list<list<array{0: float, 1: float}>>, limites: array{minLon: float, maxLon: float, minLat: float, maxLat: float}}|null
     */
    private function municipio(AnalisisArea $analisis): ?array
    {
        try {
            $fila = DB::selectOne(
                'select m.nombre, ST_AsGeoJSON(ST_Transform(lm.geom, 4326)) as geojson
                 from suelos.limite_municipio lm
                 join municipios m on m.id = lm.municipio_id
                 where ST_Intersects(lm.geom, (select geom from inteligencia.analisis_area where id = ?))
                 limit 1',
                [$analisis->id],
            );
        } catch (Throwable) {
            return null;
        }

        $anillos = $this->anillos(is_string($fila->geojson ?? null) ? $fila->geojson : null);

        if ($anillos === []) {
            return null;
        }

        return [
            'nombre' => is_string($fila->nombre ?? null) && $fila->nombre !== '' ? $fila->nombre : 'el municipio',
            'anillos' => $anillos,
            'limites' => $this->limites($anillos),
        ];
    }

    private function texto(\GdImage $lienzo, string $texto, int $x, int $y, int $tamano, int $color): void
    {
        $fuente = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');

        if (is_file($fuente)) {
            imagettftext($lienzo, $tamano, 0, $x, $y, $color, $fuente, $texto);

            return;
        }

        imagestring($lienzo, 3, $x, $y - 12, $texto, $color);
    }

    private function anchoPagina(): int
    {
        return max(1, (int) config('inteligencia.mapa.ancho_css', 700));
    }

    private function escala(): int
    {
        return max(2, (int) config('inteligencia.mapa.escala', 2));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function medidaCss(string $binario): array
    {
        $imagen = imagecreatefromstring($binario);

        if ($imagen === false) {
            return [$this->anchoPagina(), 1];
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        imagedestroy($imagen);
        $escala = $this->escala();
        $pagina = $this->anchoPagina();
        $anchoCss = intdiv($ancho, $escala);

        if ($ancho >= $pagina * $escala) {
            $anchoCss = $pagina;
        }

        $anchoCss = max(1, min($anchoCss, $ancho));
        $altoCss = max(1, intdiv($anchoCss * $alto, max(1, $ancho)));

        return [$anchoCss, $altoCss];
    }

    /**
     * @param  list<array{tipo: string, color: string}>  $leyenda
     */
    private function htmlImagen(string $base64, string $atribucion, Carbon $capturado, ?string $nota, array $leyenda = []): string
    {
        $binario = base64_decode($base64, true);
        [$ancho, $alto] = $this->medidaCss(is_string($binario) ? $binario : '');
        $fecha = $capturado->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
        $alt = $leyenda === [] ? 'Mapa del polígono analizado' : 'Mapa de la intersección de la capa';
        $html = '<img src="data:image/png;base64,'.$base64.'" alt="'.e($alt).'" style="width: '.$ancho.'px; height: '.$alto.'px;">';

        if ($leyenda === []) {
            $html .= '<p>Polígono analizado en verde y cruces de las capas sobre el mapa base.</p>';
        } else {
            $html .= '<table>';

            foreach ($leyenda as $entrada) {
                $html .= '<tr><td style="background: '.e($entrada['color']).'; width: 14px;">&nbsp;</td><td>'.e($entrada['tipo']).'</td></tr>';
            }

            $html .= '</table><p>Polígono analizado en verde. Solo se dibuja la intersección de esta capa.</p>';
        }

        $html .= '<p>Norte hacia arriba. La escala gráfica está en el mapa.</p>'
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
