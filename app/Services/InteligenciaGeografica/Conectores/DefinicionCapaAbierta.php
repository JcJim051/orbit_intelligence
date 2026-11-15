<?php

namespace App\Services\InteligenciaGeografica\Conectores;

/**
 * Fuente verificada de una capa abierta.
 *
 * @phpstan-type Endpoint array{nombre: string, clave: string, url: string, where: string, campo_id: string, bbox: bool}
 */
final class DefinicionCapaAbierta
{
    /**
     * @param  list<Endpoint>  $endpoints
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox
     */
    public function __construct(
        public string $codigo,
        public bool $disponible,
        public string $licencia,
        public string $cita,
        public string $observacion,
        public ?string $urlServicio,
        public ?string $layerId,
        public array $endpoints,
        public ?string $motivoNoDisponible = null,
        public ?string $itemUrl = null,
        public ?string $descripcionUrl = null,
        public bool $fechaEnDescripcion = false,
        public bool $fechaEnZipDeLaDescripcion = false,
        public ?string $recorteUrl = null,
        public ?array $bbox = null,
        public array $capasPaquete = [],
    ) {}

    /**
     * @return list<array{nombre: string, layer_id: string, url_servicio: string}>
     */
    public function endpointsDeCatalogo(): array
    {
        return array_map(fn (array $endpoint): array => [
            'nombre' => $endpoint['nombre'],
            'layer_id' => $endpoint['clave'],
            'url_servicio' => $endpoint['url'],
        ], $this->endpoints);
    }
}
