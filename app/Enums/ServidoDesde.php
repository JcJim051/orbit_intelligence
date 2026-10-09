<?php

namespace App\Enums;

enum ServidoDesde: string
{
    case Copia = 'copia';
    case FuenteDirecta = 'fuente_directa';
    case CopiaPorFallaFuente = 'copia_por_falla_fuente';

    public function label(): string
    {
        return match ($this) {
            self::Copia => 'Copia local',
            self::FuenteDirecta => 'Fuente directa',
            self::CopiaPorFallaFuente => 'Copia local porque la fuente falló',
        };
    }
}
