<?php

namespace App\Enums;

enum GeoLayerAccessPolicy: string
{
    case Pending = 'pending';
    case Downloadable = 'downloadable';
    case ViewOnly = 'view_only';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de clasificación',
            self::Downloadable => 'Consulta y descarga pública',
            self::ViewOnly => 'Solo visualización',
        };
    }
}
