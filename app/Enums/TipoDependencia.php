<?php

namespace App\Enums;

enum TipoDependencia: string
{
    case Central = 'central';
    case Descentralizado = 'descentralizado';
    case PorConfirmar = 'por_confirmar';

    public function label(): string
    {
        return match ($this) {
            self::Central => 'Central',
            self::Descentralizado => 'Descentralizado',
            self::PorConfirmar => 'Por confirmar',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
