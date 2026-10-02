<?php

namespace App\Enums;

enum OrientacionIndicador: string
{
    case Incremento = 'incremento';
    case Reduccion = 'reduccion';
    case Mantenimiento = 'mantenimiento';

    public function label(): string
    {
        return match ($this) {
            self::Incremento => 'Incremento',
            self::Reduccion => 'Reducción',
            self::Mantenimiento => 'Mantenimiento',
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
