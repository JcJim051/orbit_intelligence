<?php

namespace App\Services\Analitica;

class ResultadoCargaPasiva
{
    public function __construct(
        public readonly string $estado,
        public readonly int $rubros,
        public readonly string $corte,
        public readonly string $sha256,
    ) {}
}
