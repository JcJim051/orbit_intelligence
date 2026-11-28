<?php

namespace App\Enums;

enum BankRequestStatus: string
{
    case Recibido = 'recibido';
    case EnProceso = 'en_proceso';
    case Devuelto = 'devuelto';
    case Aceptado = 'aceptado';

    public function label(): string
    {
        return match ($this) {
            self::Recibido => 'Recibido',
            self::EnProceso => 'En proceso',
            self::Devuelto => 'Devuelto',
            self::Aceptado => 'Aceptado',
        };
    }
}
