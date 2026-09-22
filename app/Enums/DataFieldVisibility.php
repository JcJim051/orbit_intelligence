<?php

namespace App\Enums;

enum DataFieldVisibility: string
{
    case Public = 'public';
    case Analytics = 'analytics';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Público',
            self::Analytics => 'Disponible para análisis',
            self::Internal => 'Interno',
        };
    }
}
