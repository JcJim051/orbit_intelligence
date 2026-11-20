<?php

namespace App\Services\Intelligence\IndicadoresTerritoriales;

use App\Models\IndicadorTerritorial;
use App\Services\Intelligence\CatalogWorkbook;

class PlantillaHistoricoIndicador
{
    public function __construct(private readonly CatalogWorkbook $workbook) {}

    public function write(IndicadorTerritorial $indicador, string $path): void
    {
        $this->workbook->write(
            $path,
            'Serie histórica',
            ['PERIODO', 'VALOR', 'FECHA_INICIO', 'FECHA_FIN', 'FECHA_CORTE', 'FUENTE', 'ENLACE_EVIDENCIA', 'OBSERVACION'],
            [['2024', null, '2024-01-01', '2024-12-31', '2024-12-31', $indicador->fuente_informacion, null, null]],
            [
                "Esta plantilla corresponde al indicador {$indicador->codigo}. Use una fila por periodo.",
                'PERIODO es la etiqueta visible de la serie: 2024, 2025-01, 2025-T1, 2025-S1, entre otros.',
                'FECHA_INICIO y FECHA_FIN deben usar el formato AAAA-MM-DD y permiten ordenar cronológicamente la serie.',
                'VALOR es el dato oficial calculado y reportado por la entidad productora. SIID no recalcula el indicador.',
                'Cada periodo puede aparecer una sola vez en la plantilla. La carga se registra para Meta, con desagregación total.',
                'Las filas cargadas quedan pendientes de validación por el enlace de información.',
            ],
        );
    }
}
