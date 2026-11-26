<?php

namespace App\Contracts\Micrositio;

interface FuenteDatosMapaInicio
{
    /**
     * @return array{version: string, departamento: array<string, mixed>}
     */
    public function metadatos(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function capas(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function capa(string $codigo): ?array;

    /**
     * @return array<string, mixed>
     */
    public function geometriaMunicipios(): array;

    /**
     * @return array<string, mixed>
     */
    public function geometriaColombia(): array;
}
