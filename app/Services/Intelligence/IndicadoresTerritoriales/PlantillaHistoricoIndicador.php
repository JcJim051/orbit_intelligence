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
            ['CODIGO_INDICADOR', 'TERRITORIO_CODIGO', 'PERIODO', 'FECHA_INICIO', 'FECHA_FIN', 'DESAGREGACION_TIPO', 'DESAGREGACION_VALOR', 'VALOR', 'NUMERADOR', 'DENOMINADOR', 'PRELIMINAR', 'FECHA_CORTE', 'FUENTE', 'ENLACE_EVIDENCIA', 'OBSERVACION'],
            [[$indicador->codigo, '50', '2024', '2024-01-01', '2024-12-31', 'Total', 'Total', null, null, null, 'NO', '2024-12-31', $indicador->fuente_informacion, null, null]],
            [
                'Use una fila por periodo y conserve el código del indicador.',
                'PERIODO es la etiqueta visible de la serie: 2024, 2025-01, 2025-T1, 2025-S1, entre otros.',
                'FECHA_INICIO y FECHA_FIN deben usar el formato AAAA-MM-DD y permiten ordenar cronológicamente la serie.',
                'VALOR es el dato oficial calculado y reportado por la entidad productora. SIID no recalcula el indicador.',
                'TERRITORIO_CODIGO usa DIVIPOLA: 00 Colombia, 50 Meta o el código de uno de los 29 municipios.',
                'La combinación indicador, territorio, periodo y desagregación no puede repetirse.',
                'Las filas cargadas quedan pendientes de validación por el enlace de información.',
            ],
        );
    }
}
