<?php

namespace App\Enums;

enum EstadoSincronizacionCapa: string
{
    case EnProceso = 'en_proceso';
    case Exitoso = 'exitoso';
    case SinCambios = 'sin_cambios';
    case RepublicadoSinCambios = 'republicado_sin_cambios';
    case Fallido = 'fallido';
    case FuenteNoDisponible = 'fuente_no_disponible';

    public function label(): string
    {
        return match ($this) {
            self::EnProceso => 'En proceso',
            self::Exitoso => 'Exitoso',
            self::SinCambios => 'Sin cambios',
            self::RepublicadoSinCambios => 'Republicado sin cambios',
            self::Fallido => 'Fallido',
            self::FuenteNoDisponible => 'Fuente no disponible',
        };
    }

    /**
     * Corridas que dejan una copia que se puede citar.
     *
     * @return list<self>
     */
    public static function atribuibles(): array
    {
        return [self::Exitoso, self::SinCambios, self::RepublicadoSinCambios];
    }
}
