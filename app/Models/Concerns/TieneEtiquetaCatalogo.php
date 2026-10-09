<?php

namespace App\Models\Concerns;

/**
 * Catálogo con código oficial y nombre. La etiqueta visible es "código — nombre".
 */
trait TieneEtiquetaCatalogo
{
    public function etiqueta(): string
    {
        return $this->codigo.' — '.$this->nombre;
    }
}
