<?php

namespace App\Enums;

enum ConsistenciaEstrato: string
{
    case MuyBlanda = 'MUY_BLANDA';
    case Blanda = 'BLANDA';
    case Media = 'MEDIA';
    case Firme = 'FIRME';
    case MuyFirme = 'MUY_FIRME';
    case Dura = 'DURA';
}
