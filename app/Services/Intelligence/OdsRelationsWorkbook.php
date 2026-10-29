<?php

namespace App\Services\Intelligence;

use App\Models\Seguimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

class OdsRelationsWorkbook
{
    /**
     * @param  Collection<int, array<string, mixed>>  $dependencyRows
     */
    public function write(string $path, Collection $dependencyRows, Seguimiento $followUp): void
    {
        $detailRows = $this->detailRows($dependencyRows);
        $productIds = $detailRows->pluck('product_id')->filter()->unique()->values();
        [$assigned, $committed] = $this->financialValues($productIds, $followUp);

        $writer = new Writer;
        $writer->openToFile($path);
        $this->writeDetailSheet($writer, $detailRows, $followUp, $assigned, $committed);
        $writer->addNewSheetAndMakeItCurrent()->setName('Resumen financiero');
        $this->writeFinancialSheet($writer, $detailRows, $followUp, $assigned, $committed);
        $writer->close();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $dependencyRows
     * @return Collection<int, array<string, mixed>>
     */
    private function detailRows(Collection $dependencyRows): Collection
    {
        return $dependencyRows->flatMap(function (array $dependencyRow): Collection {
            $dependency = $dependencyRow['dependency'];

            return $dependencyRow['details']->flatMap(function (array $detail) use ($dependency): Collection {
                $relation = $detail['relation'];
                $ods = $relation->odsIndicator;

                return $detail['metas']->flatMap(function (array $metaDetail) use ($dependency, $relation, $ods): Collection {
                    $meta = $metaDetail['meta'];

                    return $metaDetail['productos']->map(fn ($product): array => [
                        'dependency_id' => (int) $dependency->id,
                        'dependency_code' => (string) $dependency->codigo,
                        'dependency_acronym' => (string) ($dependency->sigla ?? ''),
                        'dependency_name' => (string) $dependency->nombre,
                        'goal_id' => (int) ($ods?->target?->goal?->id ?? 0),
                        'goal_code' => (string) ($ods?->target?->goal?->code ?? ''),
                        'goal_name' => (string) ($ods?->target?->goal?->name ?? ''),
                        'target_code' => (string) ($ods?->target?->code ?? ''),
                        'target_name' => (string) ($ods?->target?->name ?? ''),
                        'ods_indicator_id' => (int) ($ods?->id ?? 0),
                        'ods_indicator_code' => (string) ($ods?->code ?? ''),
                        'ods_indicator_name' => (string) ($ods?->name ?? ''),
                        'result_indicator_id' => (int) ($relation->indicador?->id ?? 0),
                        'result_indicator_code' => (string) ($relation->indicador?->codigo ?? ''),
                        'result_indicator_name' => (string) ($relation->indicador?->nombre ?? ''),
                        'result_goal_id' => (int) $meta->id,
                        'result_goal_code' => (string) ($meta->codigo_provisional ?? $meta->codigo ?? ''),
                        'result_goal_name' => (string) $meta->descripcion,
                        'product_id' => (int) $product->id,
                        'product_code' => (string) $product->codigo,
                        'product_name' => (string) $product->nombre,
                    ]);
                });
            });
        })->values();
    }

    /**
     * @param  Collection<int, int>  $productIds
     * @return array{Collection<string, float>, Collection<string, float>}
     */
    private function financialValues(Collection $productIds, Seguimiento $followUp): array
    {
        if ($productIds->isEmpty()) {
            return [collect(), collect()];
        }

        $assigned = DB::table('actividad_programaciones as programming')
            ->join('actividades as activity', 'activity.id', '=', 'programming.actividad_id')
            ->where('programming.vigencia', $followUp->vigencia)
            ->whereNull('activity.deleted_at')
            ->whereIn('activity.meta_producto_id', $productIds)
            ->groupBy('activity.dependencia_id', 'activity.meta_producto_id')
            ->selectRaw('activity.dependencia_id, activity.meta_producto_id, SUM(programming.valor_asignado) as total')
            ->get()
            ->mapWithKeys(fn ($row): array => [$this->financialKey((int) $row->dependencia_id, (int) $row->meta_producto_id) => (float) $row->total]);

        $committed = DB::table('ejecuciones_financieras as execution')
            ->join('reportes_proyecto as report', 'report.id', '=', 'execution.reporte_proyecto_id')
            ->join('actividades as activity', 'activity.id', '=', 'execution.actividad_id')
            ->where('report.seguimiento_id', $followUp->id)
            ->whereNull('activity.deleted_at')
            ->whereIn('activity.meta_producto_id', $productIds)
            ->groupBy('report.dependencia_id', 'activity.meta_producto_id')
            ->selectRaw('report.dependencia_id, activity.meta_producto_id, SUM(execution.comprometido) as total')
            ->get()
            ->mapWithKeys(fn ($row): array => [$this->financialKey((int) $row->dependencia_id, (int) $row->meta_producto_id) => (float) $row->total]);

        return [$assigned, $committed];
    }

    private function writeDetailSheet(Writer $writer, Collection $rows, Seguimiento $followUp, Collection $assigned, Collection $committed): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('ODS por dependencia');
        $headers = [
            'Código dependencia', 'Sigla', 'Dependencia', 'ODS', 'Nombre del ODS', 'Meta ODS', 'Nombre de la meta ODS',
            'Indicador ODS', 'Nombre del indicador ODS', 'Indicador resultado', 'Nombre del indicador resultado',
            'Meta resultado', 'Descripción de la meta resultado', 'Meta producto', 'Nombre de la meta producto',
            'Estado de la relación', 'Vigencia financiera', 'Fecha de corte', 'Asignado relacionado', 'Comprometido relacionado',
        ];

        $this->writeHeading($writer, 'Informe ODS por dependencia', $followUp, 'Detalle de relaciones confirmadas. Los valores financieros corresponden a la meta producto y pueden aparecer en más de un ODS cuando la relación es transversal.');
        $writer->addRow(Row::fromValues($headers, $this->headerStyle())->setHeight(34));

        foreach ($rows as $row) {
            $key = $this->financialKey($row['dependency_id'], $row['product_id']);
            $writer->addRow(Row::fromValuesWithStyles([
                $row['dependency_code'], $row['dependency_acronym'], $row['dependency_name'], $row['goal_code'], $row['goal_name'],
                $row['target_code'], $row['target_name'], $row['ods_indicator_code'], $row['ods_indicator_name'],
                $row['result_indicator_code'], $row['result_indicator_name'], $row['result_goal_code'], $row['result_goal_name'],
                $row['product_code'], $row['product_name'], 'Confirmada', $followUp->vigencia, $followUp->fecha_corte,
                (float) ($assigned[$key] ?? 0), (float) ($committed[$key] ?? 0),
            ], $this->bodyStyle(), [17 => $this->dateStyle(), 18 => $this->moneyStyle(), 19 => $this->moneyStyle()]));
        }

        $this->configureSheet($sheet, count($headers), $rows->count() + 5, [1 => 18, 2 => 14, 3 => 34, 4 => 10, 5 => 34, 6 => 12, 7 => 38, 8 => 16, 9 => 42, 10 => 18, 11 => 42, 12 => 16, 13 => 42, 14 => 18, 15 => 45, 16 => 18, 17 => 14, 18 => 15, 19 => 20, 20 => 22]);
    }

    private function writeFinancialSheet(Writer $writer, Collection $rows, Seguimiento $followUp, Collection $assigned, Collection $committed): void
    {
        $sheet = $writer->getCurrentSheet();
        $headers = ['Código dependencia', 'Sigla', 'Dependencia', 'ODS', 'Nombre del ODS', 'Indicadores ODS', 'Indicadores resultado', 'Metas resultado', 'Metas producto', 'Asignado relacionado', 'Comprometido relacionado', 'Fecha de corte'];
        $this->writeHeading($writer, 'Resumen financiero por dependencia y ODS', $followUp, 'Cada meta producto se suma una sola vez dentro de cada ODS. No sume los ODS entre sí cuando una meta esté relacionada con varios objetivos.');
        $writer->addRow(Row::fromValues($headers, $this->headerStyle())->setHeight(34));

        $groups = $rows->groupBy(fn (array $row): string => $row['dependency_id'].'-'.$row['goal_id']);
        foreach ($groups as $group) {
            $first = $group->first();
            $products = $group->unique('product_id');
            $assignedTotal = $products->sum(fn (array $row): float => (float) ($assigned[$this->financialKey($row['dependency_id'], $row['product_id'])] ?? 0));
            $committedTotal = $products->sum(fn (array $row): float => (float) ($committed[$this->financialKey($row['dependency_id'], $row['product_id'])] ?? 0));
            $writer->addRow(Row::fromValuesWithStyles([
                $first['dependency_code'], $first['dependency_acronym'], $first['dependency_name'], $first['goal_code'], $first['goal_name'],
                $group->pluck('ods_indicator_id')->filter()->unique()->count(),
                $group->pluck('result_indicator_id')->filter()->unique()->count(),
                $group->pluck('result_goal_id')->filter()->unique()->count(),
                $products->count(), $assignedTotal, $committedTotal, $followUp->fecha_corte,
            ], $this->bodyStyle(), [9 => $this->moneyStyle(), 10 => $this->moneyStyle(), 11 => $this->dateStyle()]));
        }

        $this->configureSheet($sheet, count($headers), $groups->count() + 5, [1 => 18, 2 => 14, 3 => 38, 4 => 10, 5 => 40, 6 => 18, 7 => 20, 8 => 16, 9 => 15, 10 => 22, 11 => 24, 12 => 15]);
    }

    private function writeHeading(Writer $writer, string $title, Seguimiento $followUp, string $note): void
    {
        $writer->addRow(Row::fromValues([$title], $this->titleStyle())->setHeight(28));
        $writer->addRow(Row::fromValues(['Corte: '.$followUp->etiqueta().' al '.$followUp->fecha_corte->format('d/m/Y')], $this->subtitleStyle()));
        $writer->addRow(Row::fromValues([$note], $this->noteStyle())->setHeight(30));
        $writer->addRow(Row::fromValues([]));
    }

    /** @param array<int, float> $widths */
    private function configureSheet($sheet, int $columnCount, int $lastRow, array $widths): void
    {
        foreach ($widths as $column => $width) {
            $sheet->setColumnWidth($width, $column);
        }
        $sheet->setAutoFilter(new AutoFilter(0, 5, $columnCount - 1, max(5, $lastRow)));
        $sheet->setSheetView((new SheetView)->setFreezeRow(6)->setFreezeColumn('D'));
        $sheet->setPrintTitleRows('1:5');
    }

    private function financialKey(int $dependencyId, int $productId): string
    {
        return $dependencyId.'-'.$productId;
    }

    private function titleStyle(): Style
    {
        return (new Style)->setFontBold()->setFontSize(16)->setFontColor(Color::rgb(0, 100, 68));
    }

    private function subtitleStyle(): Style
    {
        return (new Style)->setFontBold()->setFontColor(Color::rgb(71, 85, 105));
    }

    private function noteStyle(): Style
    {
        return (new Style)->setFontItalic()->setFontColor(Color::rgb(146, 64, 14))->setShouldWrapText();
    }

    private function headerStyle(): Style
    {
        return (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor(Color::rgb(0, 100, 68))->setShouldWrapText();
    }

    private function bodyStyle(): Style
    {
        return (new Style)->setShouldWrapText();
    }

    private function moneyStyle(): Style
    {
        return (new Style)->setFormat('$#,##0.00');
    }

    private function dateStyle(): Style
    {
        return (new Style)->setFormat('dd/mm/yyyy');
    }
}
