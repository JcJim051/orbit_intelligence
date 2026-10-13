<?php

namespace App\Enums;

enum EstadoSincronizacionCapa: string
{
    case EnProceso = 'en_proceso';
    case Exitoso = 'exitoso';
    case SinCambios = 'sin_cambios';
    case Fallido = 'fallido';
    case FuenteNoDisponible = 'fuente_no_disponible';

    public function label(): string
    {
        return match ($this) {
            self::EnProceso => 'En proceso',
            self::Exitoso => 'Exitoso',
            self::SinCambios => 'Sin cambios',
            self::Fallido => 'Fallido',
            self::FuenteNoDisponible => 'Fuente no disponible',
        };
    }
}
