<?php

namespace App\Enums;

enum FrecuenciaActualizacionCapa: string
{
    case Diaria = 'diaria';
    case Semanal = 'semanal';
    case Mensual = 'mensual';
    case PorVersion = 'por_version';
    case TiempoReal = 'tiempo_real';

    public function label(): string
    {
        return match ($this) {
            self::Diaria => 'Diaria',
            self::Semanal => 'Semanal',
            self::Mensual => 'Mensual',
            self::PorVersion => 'Por versión',
            self::TiempoReal => 'Tiempo real',
        };
    }
}
