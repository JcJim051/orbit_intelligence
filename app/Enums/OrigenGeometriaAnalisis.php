<?php

namespace App\Enums;

enum OrigenGeometriaAnalisis: string
{
    case Dibujo = 'dibujo';
    case Kmz = 'kmz';

    public function label(): string
    {
        return match ($this) {
            self::Dibujo => 'Polígono dibujado',
            self::Kmz => 'KMZ cargado',
        };
    }
}
