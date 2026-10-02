<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\MetaResultado;
use App\Models\PddPilar;
use App\Models\PddPrograma;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetaResultadoPorPilarController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', MetaResultado::class);

        $filters = $this->filters($request);
        $metas = $this->metas($filters);

        return view('intelligence.metas-resultado.por-pilar', [
            'filters' => $filters,
            'pilares' => PddPilar::query()->orderBy('codigo')->get(['id', 'codigo', 'numeral', 'nombre']),
            'grupos' => $this->agruparPorPilar($metas),
            'totalMetas' => $metas->count(),
            'totalPilares' => $this->agruparPorPilar($metas)->count(),
            'totalProgramas' => $metas->map(fn (MetaResultado $meta) => $this->programaDe($meta)?->id)->filter()->unique()->count(),
            'totalMetasProducto' => $metas->sum('metas_producto_count'),
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', MetaResultado::class);

        $metas = $this->metas($this->filters($request));
        $filename = 'metas-resultado-por-pilar-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($metas): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'pilar_codigo',
                'pilar_numeral',
                'pilar_nombre',
                'meta_codigo',
                'meta_descripcion',
                'indicador_resultado',
                'unidad_medida',
                'programa_codigo',
                'programa_numeral',
                'programa_nombre',
                'subprograma_codigo',
                'subprograma_numeral',
                'subprograma_nombre',
                'linea_base',
                'meta_cuatrienio',
                'metas_producto_asociadas',
                'estado',
            ], ';');

            foreach ($metas as $meta) {
                $pilar = $this->pilarDe($meta);
                $programa = $this->programaDe($meta);
                $subprograma = $meta->subprograma;

                fputcsv($handle, [
                    $this->csvValue($pilar?->codigo),
                    $this->csvValue($pilar?->numeral),
                    $this->csvValue($pilar?->nombre),
                    $this->csvValue($meta->codigo_provisional ?? $meta->codigo),
                    $this->csvValue($meta->descripcion),
                    $this->csvValue($meta->indicador?->nombre),
                    $this->csvValue($meta->indicador?->unidad_medida),
                    $this->csvValue($programa?->codigo),
                    $this->csvValue($programa?->numeral),
                    $this->csvValue($programa?->nombre),
                    $this->csvValue($subprograma?->codigo),
                    $this->csvValue($subprograma?->numeral),
                    $this->csvValue($subprograma?->nombre),
                    $meta->linea_base,
                    $meta->meta_cuatrienio,
                    $meta->metas_producto_count,
                    $meta->activo ? 'Activa' : 'Inactiva',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{q: string, pilar_id: string, activo: string}  $filters
     * @return Collection<int, MetaResultado>
     */
    private function metas(array $filters): Collection
    {
        $query = MetaResultado::query()
            ->with([
                'indicador',
                'metasProducto:id,meta_resultado_id,codigo,nombre,dependencia_id,sector_mga_id,activo',
                'metasProducto.dependencia:id,nombre',
                'metasProducto.sectorMga:id,nombre',
                'programa.linea.eje.pilar',
                'subprograma.programa.linea.eje.pilar',
            ])
            ->withCount('metasProducto');

        if (in_array($filters['activo'], ['0', '1'], true)) {
            $query->where('activo', $filters['activo'] === '1');
        }

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('codigo', 'like', "%{$search}%")
                    ->orWhere('codigo_provisional', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%")
                    ->orWhereHas('indicador', fn ($query) => $query->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('programa', fn ($query) => $query->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('subprograma', fn ($query) => $query->where('nombre', 'like', "%{$search}%"));
            });
        }

        if (ctype_digit($filters['pilar_id'])) {
            $query->where(function ($query) use ($filters): void {
                $pilarId = (int) $filters['pilar_id'];
                $query
                    ->whereHas('programa.linea.eje', fn ($query) => $query->where('pilar_id', $pilarId))
                    ->orWhereHas('subprograma.programa.linea.eje', fn ($query) => $query->where('pilar_id', $pilarId));
            });
        }

        /** @var Collection<int, MetaResultado> $metas */
        return $query
            ->get()
            ->sortBy([
                fn (MetaResultado $meta): string => (string) ($this->pilarDe($meta)?->codigo ?? 'ZZZ'),
                fn (MetaResultado $meta): string => (string) ($this->programaDe($meta)?->codigo ?? 'ZZZ'),
                fn (MetaResultado $meta): string => (string) ($meta->codigo_provisional ?? $meta->codigo ?? ''),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, MetaResultado>  $metas
     */
    private function agruparPorPilar(Collection $metas): Collection
    {
        return $metas
            ->groupBy(fn (MetaResultado $meta): string => (string) ($this->pilarDe($meta)?->getKey() ?? 'sin-pilar'))
            ->map(function (Collection $metasDelPilar): array {
                /** @var MetaResultado $primera */
                $primera = $metasDelPilar->first();
                $pilar = $this->pilarDe($primera);

                return [
                    'pilar' => $pilar,
                    'metas' => $metasDelPilar,
                    'programas' => $metasDelPilar->map(fn (MetaResultado $meta) => $this->programaDe($meta)?->id)->filter()->unique()->count(),
                    'subprogramas' => $metasDelPilar->pluck('subprograma_id')->filter()->unique()->count(),
                    'metas_producto' => $metasDelPilar->sum('metas_producto_count'),
                ];
            })
            ->values();
    }

    /**
     * @return array{q: string, pilar_id: string, activo: string}
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'pilar_id' => (string) $request->query('pilar_id', ''),
            'activo' => (string) $request->query('activo', '1'),
        ];
    }

    private function csvValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return Str::startsWith($value, ['=', '+', '-', '@']) ? "'{$value}" : $value;
    }

    private function pilarDe(MetaResultado $meta): ?PddPilar
    {
        return $this->programaDe($meta)?->linea?->eje?->pilar;
    }

    private function programaDe(MetaResultado $meta): ?PddPrograma
    {
        return $meta->subprograma?->programa ?? $meta->programa;
    }
}
