<?php

namespace App\Services\Micrositio\Mapa;

use App\Contracts\Micrositio\FuenteDatosMapaInicio;
use InvalidArgumentException;
use JsonException;

class FuenteJsonMapaInicio implements FuenteDatosMapaInicio
{
    /** @var array<string, mixed>|null */
    private ?array $datos = null;

    /** @var array<string, mixed>|null */
    private ?array $municipios = null;

    /** @var array<string, mixed>|null */
    private ?array $colombia = null;

    public function __construct(private readonly ?string $directorio = null) {}

    /**
     * @return array{version: string, departamento: array<string, mixed>}
     */
    public function metadatos(): array
    {
        $datos = $this->datos();

        return [
            'version' => (string) ($datos['version'] ?? ''),
            'departamento' => is_array($datos['departamento'] ?? null) ? $datos['departamento'] : ['divipola' => '50', 'nombre' => 'Meta'],
        ];
    }

    public function capas(): array
    {
        $capas = $this->datos()['capas'] ?? null;

        if (! is_array($capas)) {
            throw new InvalidArgumentException('El JSON del mapa no trae la lista de capas.');
        }

        return array_values($capas);
    }

    public function capa(string $codigo): ?array
    {
        foreach ($this->capas() as $capa) {
            if (($capa['codigo'] ?? null) === $codigo) {
                return $capa;
            }
        }

        return null;
    }

    public function geometriaMunicipios(): array
    {
        return $this->municipios ??= $this->geojson('meta-municipios.geojson');
    }

    public function geometriaColombia(): array
    {
        return $this->colombia ??= $this->geojson('colombia-departamentos-mini.geojson');
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(): array
    {
        if ($this->datos !== null) {
            return $this->datos;
        }

        $datos = $this->leer('datos-mapa-inicio.json');
        $this->validar($datos);

        return $this->datos = $datos;
    }

    /**
     * @return array<string, mixed>
     */
    private function geojson(string $archivo): array
    {
        $geojson = $this->leer($archivo);

        if (($geojson['type'] ?? null) !== 'FeatureCollection' || ! is_array($geojson['features'] ?? null)) {
            throw new InvalidArgumentException('El archivo '.$archivo.' no es un GeoJSON de municipios válido.');
        }

        return $geojson;
    }

    /**
     * @return array<string, mixed>
     */
    private function leer(string $archivo): array
    {
        $ruta = $this->ruta($archivo);

        if (! is_file($ruta)) {
            throw new InvalidArgumentException('No se encontró el archivo del mapa: '.$archivo.'.');
        }

        try {
            $datos = json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('El archivo '.$archivo.' no es un JSON válido: '.$exception->getMessage(), 0, $exception);
        }

        if (! is_array($datos)) {
            throw new InvalidArgumentException('El archivo '.$archivo.' no contiene un objeto JSON.');
        }

        return $datos;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validar(array $datos): void
    {
        if (! is_array($datos['capas'] ?? null) || $datos['capas'] === []) {
            throw new InvalidArgumentException('El JSON del mapa no trae capas.');
        }

        foreach ($datos['capas'] as $indice => $capa) {
            if (! is_array($capa)) {
                throw new InvalidArgumentException('La capa '.$indice.' del mapa no tiene la estructura esperada.');
            }

            $codigo = (string) ($capa['codigo'] ?? '');

            if ($codigo === '') {
                throw new InvalidArgumentException('Una capa del mapa no tiene código.');
            }

            $unidad = (string) ($capa['unidad'] ?? '');

            if (! in_array($unidad, ['%', 'habitantes'], true)) {
                throw new InvalidArgumentException('La capa '.$codigo.' tiene una unidad no válida ('.$unidad.').');
            }

            $valores = $capa['valores'] ?? null;

            if (! is_array($valores) || count($valores) !== 29) {
                throw new InvalidArgumentException('La capa '.$codigo.' debe traer los 29 municipios del Meta.');
            }

            foreach ($valores as $valor) {
                $divipola = (string) ($valor['divipola'] ?? '');

                if (! preg_match('/^50\d{3}$/', $divipola)) {
                    throw new InvalidArgumentException('La capa '.$codigo.' tiene un DIVIPOLA inválido: '.$divipola.'.');
                }

                if (! is_string($valor['municipio'] ?? null) || $valor['municipio'] === '') {
                    throw new InvalidArgumentException('La capa '.$codigo.' tiene un municipio sin nombre ('.$divipola.').');
                }
            }
        }
    }

    private function ruta(string $archivo): string
    {
        return rtrim($this->directorio ?? resource_path('data/micrositio/mapa-inicio'), '/').'/'.$archivo;
    }
}
