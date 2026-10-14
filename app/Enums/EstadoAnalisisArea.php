<?php

namespace App\Enums;

enum EstadoAnalisisArea: string
{
    case Borrador = 'borrador';
    case Procesando = 'procesando';
    case Listo = 'listo';
    case Fallido = 'fallido';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Procesando => 'Procesando',
            self::Listo => 'Listo',
            self::Fallido => 'Fallido',
        };
    }
}
