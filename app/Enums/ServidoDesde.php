<?php

namespace App\Enums;

enum ServidoDesde: string
{
    case Cache = 'cache';
    case Upstream = 'upstream';

    public function label(): string
    {
        return match ($this) {
            self::Cache => 'Caché local',
            self::Upstream => 'Servicio de origen',
        };
    }
}
