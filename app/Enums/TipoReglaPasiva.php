<?php

namespace App\Enums;

enum TipoReglaPasiva: string
{
    case UnidadPct = 'unidad_pct';
    case PrefijoRubro = 'prefijo_rubro';
    case SectorMga = 'sector_mga';
    case Bpin = 'bpin';

    public function label(): string
    {
        return match ($this) {
            self::UnidadPct => 'Unidad PCT',
            self::PrefijoRubro => 'Prefijo de rubro',
            self::SectorMga => 'Sector MGA',
            self::Bpin => 'BPIN',
        };
    }

    public function prioridadPorDefecto(): int
    {
        return match ($this) {
            self::Bpin => 400,
            self::PrefijoRubro => 300,
            self::SectorMga => 200,
            self::UnidadPct => 100,
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
