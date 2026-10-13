<?php

namespace App\Http\Controllers\Intelligence;

use App\Models\MetaProducto;
use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\IndicadorProductoCatalog;
use App\Services\Intelligence\CatalogWorkbook;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IndicadorProductoController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new IndicadorProductoCatalog;
    }

    public function template(CatalogWorkbook $workbook): BinaryFileResponse
    {
        $catalog = $this->catalog();
        $this->authorize('import', $catalog->modelClass());

        $path = tempnam(sys_get_temp_dir(), 'catalog');

        if ($path === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal.');
        }

        $metas = MetaProducto::query()
            ->with('indicadorProducto')
            ->orderBy('codigo')
            ->get();

        $rows = $metas->map(function (MetaProducto $meta): array {
            $indicador = $meta->indicadorProducto;

            return [
                $meta->codigo,
                $indicador?->codigo,
                $indicador?->nombre ?? $meta->indicador_producto,
                $indicador?->unidad_medida,
                $indicador?->tipo_acumulacion,
                $indicador?->linea_base,
                $indicador?->meta_cuatrienio,
                $indicador?->fuente_verificacion,
                $indicador?->activo === false ? 0 : 1,
            ];
        })->all();

        if ($rows === []) {
            $rows = [$catalog->exampleRow()];
        }

        $workbook->write(
            $path,
            'Datos',
            $catalog->excelHeaders(),
            $rows,
            [
                ...$catalog->instructionLines(),
                'Relación Meta producto ↔ Indicador producto: la columna meta_producto_codigo identifica la meta producto que se va a actualizar.',
                'La plantilla se descarga prediligenciada con las metas producto existentes. Complete o ajuste las columnas del indicador producto y vuelva a cargar el mismo archivo.',
                'No cambie los encabezados de la hoja Datos. Si necesita ubicar la meta, use el código y compárelo con el catálogo de metas producto.',
            ],
        );

        return response()
            ->download($path, $catalog->templateFilename())
            ->deleteFileAfterSend(true);
    }
}
