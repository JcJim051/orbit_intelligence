<?php

namespace App\Enums;

enum HumedadVisualEstrato: string
{
    case Seco = 'SECO';
    case Humedo = 'HUMEDO';
    case MuyHumedo = 'MUY_HUMEDO';
    case Saturado = 'SATURADO';
}
