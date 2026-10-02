<?php

namespace App\Enums;

enum GrupoFuenteFinanciacion: string
{
    case RecursosPropios = 'recursos_propios';
    case Sgr = 'sgr';
    case Nacion = 'nacion';
    case Otros = 'otros';

    public function label(): string
    {
        return match ($this) {
            self::RecursosPropios => 'Recursos propios',
            self::Sgr => 'SGR',
            self::Nacion => 'Nación',
            self::Otros => 'Otras fuentes',
        };
    }

    public function orden(): int
    {
        return match ($this) {
            self::RecursosPropios => 1,
            self::Sgr => 2,
            self::Nacion => 3,
            self::Otros => 4,
        };
    }

    /**
     * Clasificación inicial sugerida a partir del tipo oficial de la fuente (catálogo PCT).
     * Queda guardada como atributo del catálogo y la Gerencia la puede corregir; no se recalcula en cada consulta.
     */
    public static function sugerir(?string $codigo, ?string $tipo, ?string $nombre = null): self
    {
        $tipo = mb_strtoupper((string) $tipo);
        $nombre = mb_strtoupper((string) $nombre);

        if (str_contains($tipo, 'SGR') || str_starts_with($nombre, 'SGR')) {
            return self::Sgr;
        }

        if (str_starts_with($tipo, 'PROPIOS')) {
            return self::RecursosPropios;
        }

        if (str_starts_with($tipo, 'NACI')) {
            return self::Nacion;
        }

        return self::Otros;
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
