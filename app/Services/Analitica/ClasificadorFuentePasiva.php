<?php

namespace App\Services\Analitica;

use RuntimeException;

/**
 * Cruza el código de fuente de la pasiva con el catálogo PCT y lo agrupa
 * con las mismas reglas de load_pasiva.py: Crédito, SGP, SGR, Recursos propios,
 * Otras de la Nación o Sin catálogo.
 */
class ClasificadorFuentePasiva
{
    /** @var array<string, array{codigo: string, nombre: string, tipo: ?string}> */
    private array $catalogo;

    public function __construct(?string $rutaCatalogo = null)
    {
        $ruta = $rutaCatalogo ?? database_path('seeders/data/fuentes_financiacion.json');
        $contenido = file_get_contents($ruta);
        $fuentes = is_string($contenido) ? json_decode($contenido, true) : null;

        if (! is_array($fuentes)) {
            throw new RuntimeException('No se pudo leer el catálogo de fuentes de financiación.');
        }

        $catalogo = [];

        foreach ($fuentes as $fuente) {
            if (! is_array($fuente) || ! isset($fuente['codigo'])) {
                continue;
            }

            $catalogo[(string) $fuente['codigo']] = [
                'codigo' => (string) $fuente['codigo'],
                'nombre' => (string) ($fuente['nombre'] ?? ''),
                'tipo' => isset($fuente['tipo']) ? (string) $fuente['tipo'] : null,
            ];
        }

        $this->catalogo = $catalogo;
    }

    public function grupo(string $codigo): string
    {
        $fuente = $this->catalogo[$codigo] ?? null;

        if ($fuente === null) {
            return 'Sin catálogo';
        }

        $nombre = mb_strtoupper($fuente['nombre']);
        $tipo = mb_strtoupper((string) $fuente['tipo']);

        if (str_contains($nombre, 'CREDITO') || str_contains($nombre, 'CRÉDITO') || str_contains($tipo, 'CREDITO') || str_contains($tipo, 'CRÉDITO')) {
            return 'Crédito';
        }

        if (str_starts_with($nombre, 'SGP')) {
            return 'SGP';
        }

        if (str_starts_with($nombre, 'SGR') || str_contains($tipo, 'SGR')) {
            return 'SGR';
        }

        if (str_starts_with($tipo, 'PROPIOS')) {
            return 'Recursos propios';
        }

        return 'Otras de la Nación';
    }

    public function etiqueta(string $codigo): string
    {
        $fuente = $this->catalogo[$codigo] ?? null;

        if ($fuente === null) {
            return $codigo.' — Sin catálogo';
        }

        return $codigo.' — '.$fuente['nombre'];
    }
}
