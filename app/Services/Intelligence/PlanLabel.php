<?php

namespace App\Services\Intelligence;

class PlanLabel
{
    /**
     * @return array{numeral: ?string, nombre: string}
     */
    public static function split(string $raw): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);

        if (preg_match('/^PILAR\s+(\d+)\.?\s+(.+)$/u', $text, $matches) === 1) {
            return [
                'numeral' => $matches[1],
                'nombre' => trim($matches[2]),
            ];
        }

        if (preg_match('/^(\d+(?:\.\d+)*)\.?\s+(.+)$/u', $text, $matches) === 1) {
            return [
                'numeral' => $matches[1],
                'nombre' => trim($matches[2]),
            ];
        }

        return [
            'numeral' => null,
            'nombre' => $text,
        ];
    }

    public static function sectorName(string $raw): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);

        if (preg_match('/^Sector\s+\S+\s+-\s+(.+)$/u', $text, $matches) === 1) {
            return trim($matches[1]);
        }

        return $text;
    }
}
