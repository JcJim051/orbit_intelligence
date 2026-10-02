<?php

namespace App\Services\Intelligence\Catalogs;

use App\Models\PddEje;
use App\Models\PddLinea;
use App\Models\PddPilar;
use App\Models\PddPrograma;
use App\Models\PddSubprograma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Rótulos legibles para los nodos del Plan de Desarrollo.
 *
 * Nunca se muestra un código o numeral suelto: siempre «referencia — nombre».
 * Si el nombre ya empieza por el nivel (p. ej. «EJE ESTRATÉGICO …»), la
 * referencia es solo el numeral; si no (los pilares), se antepone el nivel.
 */
final class PlanLabel
{
    private const LEVELS = [
        PddPilar::class => 'Pilar',
        PddEje::class => 'Eje',
        PddLinea::class => 'Línea',
        PddPrograma::class => 'Programa',
        PddSubprograma::class => 'Subprograma',
    ];

    public static function supports(Model $node): bool
    {
        return isset(self::LEVELS[$node::class]);
    }

    public static function reference(Model $node): string
    {
        $numeral = trim((string) $node->getAttribute('numeral'));
        $reference = $numeral !== '' ? $numeral : (string) $node->getAttribute('codigo');
        $level = self::LEVELS[$node::class] ?? null;

        if ($level === null) {
            return $reference;
        }

        $name = Str::upper(Str::ascii((string) $node->getAttribute('nombre')));

        return str_starts_with($name, Str::upper(Str::ascii($level)))
            ? $reference
            : $level.' '.$reference;
    }

    public static function full(Model $node): string
    {
        $label = self::reference($node).' — '.$node->getAttribute('nombre');

        if (method_exists($node, 'trashed') && $node->trashed()) {
            $label .= ' (eliminado)';
        }

        return $label;
    }
}
