<?php

namespace App\Enums;

enum EstadoFrescuraCapa: string
{
    case Vigente = 'vigente';
    case PosiblementeDesactualizada = 'posiblemente_desactualizada';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::PosiblementeDesactualizada => 'Posiblemente desactualizada',
            self::Error => 'Error de sincronización',
        };
    }
}
