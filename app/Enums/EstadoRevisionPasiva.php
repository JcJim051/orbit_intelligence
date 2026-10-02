<?php

namespace App\Enums;

enum EstadoRevisionPasiva: string
{
    case Asignada = 'asignada';
    case Pendiente = 'pendiente';
    case NoAplica = 'no_aplica';

    public function label(): string
    {
        return match ($this) {
            self::Asignada => 'Asignada',
            self::Pendiente => 'Pendiente de revisión',
            self::NoAplica => 'No aplica (no es inversión)',
        };
    }
}
