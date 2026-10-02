<?php

namespace Database\Seeders;

use App\Enums\GrupoFuenteFinanciacion;
use App\Models\FuenteFinanciacion;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Carga el catálogo de fuentes del PCT (data/fuentes_financiacion.json). Idempotente por código.
 * El grupo de reporte (Recursos propios, SGR, Nación, Otros) se sugiere desde el tipo oficial
 * solo cuando la fuente aún no lo tiene; si la Gerencia lo corrigió, no se sobrescribe.
 */
class FuenteFinanciacionSeeder extends Seeder
{
    public function run(): void
    {
        $contenido = file_get_contents(database_path('seeders/data/fuentes_financiacion.json'));
        $fuentes = is_string($contenido) ? json_decode($contenido, true) : null;

        if (! is_array($fuentes)) {
            throw new RuntimeException('No se pudo leer el catálogo de fuentes de financiación.');
        }

        foreach ($fuentes as $fuente) {
            $registro = FuenteFinanciacion::withTrashed()->firstOrNew(['codigo' => (string) $fuente['codigo']]);
            $registro->fill([
                'nombre' => $fuente['nombre'],
                'tipo' => $fuente['tipo'] ?? null,
                'activo' => (bool) ($fuente['activo'] ?? true),
            ]);
            $registro->grupo_reporte ??= GrupoFuenteFinanciacion::sugerir($registro->codigo, $registro->tipo, $registro->nombre);
            $registro->save();
        }
    }
}
