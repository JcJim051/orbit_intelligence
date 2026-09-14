<?php

namespace App\Enums;

enum DatasetFormVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Published => 'Publicada',
            self::Retired => 'Retirada',
        };
    }
}
