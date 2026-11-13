<?php

namespace App\Services\Siid;

use App\Models\IndicadorTerritorial;
use App\Models\IndicadorTerritorialDato;
use App\Models\SiidTerritorio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TerritorialPublicSeries
{
    /**
     * @return array<int, array{
     *     key: string,
     *     territorio_codigo: string,
     *     territorio: string,
     *     desagregacion_tipo: string,
     *     desagregacion_valor: string,
     *     predeterminada: bool,
     *     puntos: array<int, array{periodo: string, periodo_inicio: ?string, periodo_fin: ?string, fecha_corte: ?string, valor: float, preliminar: bool}>
     * }>
     */
    public function build(IndicadorTerritorial $indicator): array
    {
        $data = $indicator->datos()
            ->where('estado', IndicadorTerritorialDato::ESTADO_APROBADO)
            ->orderByRaw('case when periodo_inicio is null then 1 else 0 end')
            ->orderBy('periodo_inicio')
            ->orderBy('periodo_fin')
            ->orderBy('periodo')
            ->get([
                'id', 'indicador_territorial_id', 'territorio_codigo', 'desagregacion_tipo',
                'desagregacion_valor', 'periodo', 'periodo_inicio', 'periodo_fin', 'fecha_corte',
                'valor', 'es_preliminar',
            ]);

        $territories = collect(['50' => 'Meta']);
        if (DB::getDriverName() === 'pgsql' && Schema::hasTable('siid_territorial.territorios')) {
            $territories = $territories->merge(SiidTerritorio::query()
                ->whereIn('codigo', $data->pluck('territorio_codigo')->filter()->unique())
                ->pluck('nombre', 'codigo'));
        }

        return $data
            ->groupBy(fn (IndicadorTerritorialDato $datum): string => implode('|', [
                $datum->territorio_codigo,
                $datum->desagregacion_tipo,
                $datum->desagregacion_valor,
            ]))
            ->map(function (Collection $points, string $key) use ($territories): array {
                /** @var IndicadorTerritorialDato $first */
                $first = $points->first();
                $isDefault = $first->territorio_codigo === '50'
                    && mb_strtolower($first->desagregacion_tipo) === 'total'
                    && mb_strtolower($first->desagregacion_valor) === 'total';

                return [
                    'key' => hash('sha256', $key),
                    'territorio_codigo' => $first->territorio_codigo,
                    'territorio' => $territories->get($first->territorio_codigo, $first->territorio_codigo),
                    'desagregacion_tipo' => $first->desagregacion_tipo,
                    'desagregacion_valor' => $first->desagregacion_valor,
                    'predeterminada' => $isDefault,
                    'puntos' => $points->map(fn (IndicadorTerritorialDato $datum): array => [
                        'periodo' => $datum->periodo,
                        'periodo_inicio' => $datum->periodo_inicio?->toDateString(),
                        'periodo_fin' => $datum->periodo_fin?->toDateString(),
                        'fecha_corte' => $datum->fecha_corte?->toDateString(),
                        'valor' => (float) $datum->valor,
                        'preliminar' => (bool) $datum->es_preliminar,
                    ])->values()->all(),
                ];
            })
            ->sortByDesc('predeterminada')
            ->values()
            ->all();
    }
}
