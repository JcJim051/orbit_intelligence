<?php

namespace App\Enums;

enum ModoAnalisis: string
{
    case Normal = 'normal';
    case Oficial = 'oficial';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Oficial => 'Oficial',
        };
    }
}
