<?php

namespace App\Enums;

enum CompacidadEstrato: string
{
    case MuySuelta = 'MUY_SUELTA';
    case Suelta = 'SUELTA';
    case Media = 'MEDIA';
    case Densa = 'DENSA';
    case MuyDensa = 'MUY_DENSA';
}
