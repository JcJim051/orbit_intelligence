<?php

namespace App\Enums;

enum EstadoRefrescoCapa: string
{
    case EnProceso = 'en_proceso';
    case Exitoso = 'exitoso';
    case Fallido = 'fallido';

    public function label(): string
    {
        return match ($this) {
            self::EnProceso => 'En proceso',
            self::Exitoso => 'Exitoso',
            self::Fallido => 'Fallido',
        };
    }
}
