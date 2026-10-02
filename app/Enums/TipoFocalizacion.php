<?php

namespace App\Enums;

enum TipoFocalizacion: string
{
    case Municipal = 'municipal';
    case Multimunicipio = 'multimunicipio';
    case Departamental = 'departamental';

    public function label(): string
    {
        return match ($this) {
            self::Municipal => 'Un solo municipio',
            self::Multimunicipio => 'Multimunicipio',
            self::Departamental => 'Departamental (META)',
        };
    }

    /**
     * Solo los proyectos multimunicipio o departamentales reportan la focalización cada mes.
     * Los de un solo municipio la tienen fija en el proyecto.
     */
    public function requiereFocalizacionMensual(): bool
    {
        return $this !== self::Municipal;
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
