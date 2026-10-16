<?php

namespace App\Services\InteligenciaGeografica;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Descarga una geodatabase publicada como zip y vuelca las capas de terrenos.
 */
class PaqueteGeodatabase
{
    private const MUNICIPIO_NO_CUBIERTO = '50001';

    /**
     * @param  list<string>  $capas
     * @return list<string>
     */
    public function volcar(string $url, array $capas, AlmacenCapa $almacen): array
    {
        $directorio = storage_path('app/igac-'.bin2hex(random_bytes(6)));
        mkdir($directorio, 0700, true);
        $zip = $directorio.'/paquete.zip';

        try {
            $this->descargar($url, $zip);
            $gdb = $this->geodatabase($zip, $directorio);
            $municipios = [];
            $excluidos = 0;

            foreach ($capas as $capa) {
                $excluidos += $this->volcarCapa($gdb, $capa, $almacen, $municipios);
            }

            $notas = [
                'Villavicencio ('.self::MUNICIPIO_NO_CUBIERTO.') no cubierto: gestor propio.',
                'Municipios con terrenos de IGAC: '.count($municipios).'.',
            ];

            if ($excluidos > 0) {
                $notas[] = 'Se excluyeron '.$excluidos.' predios del municipio '.self::MUNICIPIO_NO_CUBIERTO.'.';
            }

            return $notas;
        } finally {
            $this->borrar($directorio);
        }
    }

    private function descargar(string $url, string $destino): void
    {
        $parcial = storage_path('app/inteligencia/'.hash('sha256', $url).'.parcial');
        if (! is_dir(dirname($parcial))) {
            mkdir(dirname($parcial), 0700, true);
        }

        $connect = max(1, (int) config('inteligencia.http.connect_timeout', 30));
        $timeout = max(1, (int) config('inteligencia.http.paquete_timeout', 1800));
        $reintentos = max(0, (int) config('inteligencia.http.reintentos', 2));
        $espera = max(0, (int) config('inteligencia.http.reintento_ms', 1000));
        $ultimo = null;

        for ($intento = 0; $intento <= $reintentos; $intento++) {
            $desde = is_file($parcial) ? (int) filesize($parcial) : 0;
            $manejador = $desde > 0 ? fopen($parcial, 'ab') : null;

            try {
                $peticion = Http::withHeaders([
                    'User-Agent' => 'SIID-inteligencia/1.0',
                    'Accept' => 'application/zip',
                ])->connectTimeout($connect)->timeout($timeout)->withOptions([
                    'curl' => [
                        CURLOPT_CONNECTTIMEOUT => $connect,
                        CURLOPT_TIMEOUT => $timeout,
                    ],
                    'sink' => $manejador ?: $parcial,
                ]);

                if ($desde > 0) {
                    $peticion = $peticion->withHeaders(['Range' => 'bytes='.$desde.'-']);
                }

                $respuesta = $peticion->get($url);

                if (is_resource($manejador)) {
                    fclose($manejador);
                    $manejador = null;
                }

                if ($respuesta->status() === 200 && $desde > 0) {
                    @unlink($parcial);
                    $ultimo = new ConnectionException('El servidor no reanudó la descarga del paquete.');

                    continue;
                }

                $respuesta->throw();
                clearstatcache(true, $parcial);
                $escrito = is_file($parcial) ? (int) filesize($parcial) : 0;

                if ($escrito === 0) {
                    $cuerpo = $respuesta->body();

                    if ($cuerpo !== '') {
                        file_put_contents($parcial, $cuerpo);
                        $escrito = strlen($cuerpo);
                    }

                    unset($cuerpo);
                }

                if (! $this->paqueteCompleto($respuesta, $parcial, $escrito)) {
                    throw new ConnectionException('La descarga del paquete quedó incompleta.');
                }

                rename($parcial, $destino);

                return;
            } catch (ConnectionException $excepcion) {
                $ultimo = $excepcion;

                if (is_resource($manejador)) {
                    fclose($manejador);
                }

                if ($intento < $reintentos && $espera > 0) {
                    usleep($espera * 1000 * ($intento + 1));
                }
            }
        }

        throw $ultimo ?? new ConnectionException('La fuente no respondió.');
    }

    private function paqueteCompleto(Response $respuesta, string $ruta, int $escrito): bool
    {
        $rango = $respuesta->header('Content-Range');

        if (is_string($rango) && preg_match('#/(\d+)\s*$#', $rango, $total) === 1) {
            return $escrito === (int) $total[1] && $this->zipValido($ruta);
        }

        $largo = $respuesta->header('Content-Length');

        if ($respuesta->status() === 200 && is_numeric($largo) && (int) $largo !== $escrito) {
            return false;
        }

        return $this->zipValido($ruta);
    }

    private function zipValido(string $ruta): bool
    {
        if (! is_file($ruta) || filesize($ruta) === 0) {
            return false;
        }

        $zip = new ZipArchive;
        $codigo = $zip->open($ruta);

        if ($codigo !== true) {
            return false;
        }

        $zip->close();

        return true;
    }

    private function geodatabase(string $zip, string $directorio): string
    {
        $archivo = new ZipArchive;
        if ($archivo->open($zip) !== true) {
            throw new RuntimeException('El paquete del IGAC no es un zip legible.');
        }

        for ($indice = 0; $indice < $archivo->numFiles; $indice++) {
            $nombre = (string) $archivo->getNameIndex($indice);
            if (str_contains($nombre, '..') || str_starts_with($nombre, '/')) {
                $archivo->close();
                throw new RuntimeException('El zip del IGAC trae una ruta no permitida.');
            }
        }

        $archivo->extractTo($directorio);
        $archivo->close();
        $hallazgos = glob($directorio.'/*.gdb', GLOB_ONLYDIR) ?: [];

        if ($hallazgos === []) {
            throw new RuntimeException('El zip del IGAC no contiene una geodatabase.');
        }

        return $hallazgos[0];
    }

    /**
     * @param  array<string, true>  $municipios
     */
    private function volcarCapa(string $gdb, string $capa, AlmacenCapa $almacen, array &$municipios): int
    {
        $proceso = new Process([
            'ogr2ogr', '-f', 'GeoJSONSeq', '/vsistdout/', '-t_srs', 'EPSG:4326', $gdb, $capa,
        ]);
        $proceso->disableOutput();
        $proceso->setTimeout(max(1, (int) config('inteligencia.http.paquete_timeout', 1800)));
        $pendiente = '';
        $lote = [];
        $excluidos = 0;
        $error = '';

        $proceso->run(function (string $tipo, string $fragmento) use (&$pendiente, &$lote, &$excluidos, &$municipios, &$error, $capa, $almacen): void {
            if ($tipo !== Process::OUT) {
                if (strlen($error) < 500) {
                    $error .= $fragmento;
                }

                return;
            }

            $pendiente .= $fragmento;

            while (($corte = strpos($pendiente, "\n")) !== false) {
                $linea = substr($pendiente, 0, $corte);
                $pendiente = substr($pendiente, $corte + 1);
                $entidad = $this->entidad($linea, $capa, $municipios, $excluidos);

                if ($entidad === null) {
                    continue;
                }

                $lote[] = $entidad;

                if (count($lote) >= 40) {
                    $almacen->agregar($this->unicos($lote), 4326);
                    $lote = [];
                }
            }
        });

        if ($pendiente !== '') {
            $entidad = $this->entidad($pendiente, $capa, $municipios, $excluidos);
            if ($entidad !== null) {
                $lote[] = $entidad;
            }
        }

        if ($lote !== []) {
            $almacen->agregar($this->unicos($lote), 4326);
        }

        if (! $proceso->isSuccessful()) {
            throw new RuntimeException('No se pudo leer '.$capa.' de la geodatabase. '.trim($error));
        }

        return $excluidos;
    }

    /**
     * @param  array<string, true>  $municipios
     * @return array{id: string, atributos: array<string, mixed>, geom: string}|null
     */
    private function entidad(string $linea, string $capa, array &$municipios, int &$excluidos): ?array
    {
        $linea = trim($linea);
        if ($linea === '') {
            return null;
        }

        $feature = json_decode($linea, true);
        if (! is_array($feature) || ! is_array($feature['properties'] ?? null) || ! is_array($feature['geometry'] ?? null)) {
            return null;
        }

        /** @var array<string, mixed> $propiedades */
        $propiedades = $feature['properties'];
        $municipio = $this->texto($propiedades, 'codigo_municipio');
        $departamento = $this->texto($propiedades, 'CODIGO_DEPARTAMENTO');

        if ($departamento !== null && ! in_array($departamento, ['50', '050'], true)) {
            return null;
        }

        if ($municipio === self::MUNICIPIO_NO_CUBIERTO) {
            $excluidos++;

            return null;
        }

        $codigo = $this->texto($propiedades, 'CODIGO');
        if ($codigo === null) {
            return null;
        }

        if ($municipio !== null) {
            $municipios[$municipio] = true;
        }

        $atributos = ['_capa' => strtolower($capa)];

        foreach ($propiedades as $nombre => $valor) {
            if (! is_string($nombre) || str_starts_with(strtolower($nombre), 'shape')) {
                continue;
            }

            $atributos[$nombre] = is_scalar($valor) || $valor === null ? $valor : json_encode($valor);
        }

        $id = strtolower($capa).':'.$codigo;

        return [
            'id' => strlen($id) > 120 ? strtolower($capa).':'.substr(hash('sha256', $codigo), 0, 40) : $id,
            'atributos' => $atributos,
            'geom' => json_encode($feature['geometry'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @param  list<array{id: string, atributos: array<string, mixed>, geom: string}>  $lote
     * @return list<array{id: string, atributos: array<string, mixed>, geom: string}>
     */
    private function unicos(array $lote): array
    {
        $unicos = [];

        foreach ($lote as $entidad) {
            $unicos[$entidad['id']] = $entidad;
        }

        return array_values($unicos);
    }

    /**
     * @param  array<string, mixed>  $propiedades
     */
    private function texto(array $propiedades, string $campo): ?string
    {
        foreach ($propiedades as $nombre => $valor) {
            if (is_string($nombre) && strcasecmp($nombre, $campo) === 0 && is_scalar($valor) && (string) $valor !== '') {
                return (string) $valor;
            }
        }

        return null;
    }

    private function borrar(string $directorio): void
    {
        if (! is_dir($directorio)) {
            return;
        }

        $rutas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directorio, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($rutas as $ruta) {
            $ruta->isDir() ? rmdir($ruta->getPathname()) : unlink($ruta->getPathname());
        }

        rmdir($directorio);
    }
}
