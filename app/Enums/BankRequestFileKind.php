<?php

namespace App\Enums;

enum BankRequestFileKind: string
{
    case Formato = 'formato';
    case Anexo = 'anexo';

    public function label(): string
    {
        return match ($this) {
            self::Formato => 'Formato',
            self::Anexo => 'Anexo',
        };
    }
}
