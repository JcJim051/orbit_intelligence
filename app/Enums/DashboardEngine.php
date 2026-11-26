<?php

namespace App\Enums;

enum DashboardEngine: string
{
    case SiidNative = 'siid_native';
    case Superset = 'superset';

    public function label(): string
    {
        return match ($this) {
            self::SiidNative => 'Legado SIID',
            self::Superset => 'Superset',
        };
    }
}
