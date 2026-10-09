<?php

namespace App\Enums;

enum PotencialLicuacion: string
{
    case NoEvaluado = 'NO_EVALUADO';
    case Bajo = 'BAJO';
    case Medio = 'MEDIO';
    case Alto = 'ALTO';
}
