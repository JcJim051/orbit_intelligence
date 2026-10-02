<?php

namespace App\Enums;

enum OrigenCambioTecho: string
{
    case CargaPasiva = 'carga_pasiva';
    case AjusteManual = 'ajuste_manual';

    public function label(): string
    {
        return match ($this) {
            self::CargaPasiva => 'Calculado desde la pasiva',
            self::AjusteManual => 'Ajuste manual de la Gerencia',
        };
    }
}
