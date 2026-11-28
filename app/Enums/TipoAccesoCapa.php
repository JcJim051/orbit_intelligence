<?php

namespace App\Enums;

enum TipoAccesoCapa: string
{
    case Rest = 'rest';
    case Wfs = 'wfs';
    case Descarga = 'descarga';
    case CarguePropio = 'cargue_propio';

    public function label(): string
    {
        return match ($this) {
            self::Rest => 'Servicio REST',
            self::Wfs => 'WFS',
            self::Descarga => 'Descarga',
            self::CarguePropio => 'Cargue propio',
        };
    }
}
