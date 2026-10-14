<?php

namespace App\Enums;

enum PotencialExpansivo: string
{
    case Bajo = 'BAJO';
    case Medio = 'MEDIO';
    case Alto = 'ALTO';
    case MuyAlto = 'MUY_ALTO';
}
