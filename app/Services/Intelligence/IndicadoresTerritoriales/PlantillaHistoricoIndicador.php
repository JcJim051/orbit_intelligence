<?php

namespace App\Services\Intelligence\IndicadoresTerritoriales;

use App\Models\IndicadorTerritorial;
use App\Services\Intelligence\CatalogWorkbook;

class PlantillaHistoricoIndicador
{
    public function __construct(private readonly CatalogWorkbook $workbook) {}

    public function write(IndicadorTerritorial $indicador, string $path): void
    {
        $headers = [
            'DATO_ID', 'PERIODO', 'VALOR', 'FECHA_INICIO', 'FECHA_FIN', 'FECHA_CORTE',
            'FUENTE', 'ENLACE_EVIDENCIA', 'OBSERVACION',
        ];
        $rows = $indicador->datos()
            ->orderByRaw('periodo_inicio IS NULL')
            ->orderBy('periodo_inicio')
            ->orderBy('id')
            ->get()
            ->map(fn ($dato): array => [
                $dato->id,
                $dato->periodo,
                $dato->valor,
                $dato->periodo_inicio?->format('Y-m-d'),
                $dato->periodo_fin?->format('Y-m-d'),
                $dato->fecha_corte?->format('Y-m-d'),
                $dato->fuente,
                $dato->enlace_evidencia,
                $dato->observacion,
            ])
            ->all();

        if ($rows === []) {
            $rows[] = [
                null, '2024', null, '2024-01-01', '2024-12-31', '2024-12-31',
                $indicador->fuente_informacion, null, null,
            ];
        }

        $this->workbook->write(
            $path,
            'Serie histórica',
            $headers,
            $rows,
            [
                "Esta plantilla corresponde al indicador {$indicador->codigo}. Incluye la serie actualmente almacenada y permite corregirla mediante una nueva carga.",
                'PERIODO es la etiqueta visible de la serie: 2024, 2025-01, 2025-T1, 2025-S1, entre otros.',
                'FECHA_INICIO, FECHA_FIN y FECHA_CORTE aceptan fechas de Excel, AAAA-MM-DD o DD/MM/AAAA.',
                'VALOR es el dato oficial calculado y reportado por la entidad productora. SIID no recalcula el indicador.',
                'DATO_ID es un identificador interno. No lo modifique ni lo copie a una fila nueva.',
                'Los registros nuevos o modificados quedan pendientes de validación. Las filas eliminadas del Excel no se borran automáticamente; use la acción Eliminar en SIID.',
            ],
        );
    }
}
