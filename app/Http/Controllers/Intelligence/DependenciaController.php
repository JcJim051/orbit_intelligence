<?php

namespace App\Http\Controllers\Intelligence;

use App\Models\Dependencia;
use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\DependenciaCatalog;
use Illuminate\View\View;

class DependenciaController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new DependenciaCatalog;
    }

    public function show(Dependencia $dependencia): View
    {
        $this->authorize('view', $dependencia);

        $dependencia->load([
            'metasProducto' => fn ($query) => $query
                ->with([
                    'indicadorProducto',
                    'metaResultado.indicador',
                    'metaResultado.programa.linea.eje.pilar',
                    'metaResultado.subprograma.programa.linea.eje.pilar',
                    'sectorMga',
                    'subprograma.programa.linea.eje.pilar',
                    'proyectos.dependencias',
                ])
                ->orderBy('codigo'),
            'proyectos' => fn ($query) => $query->orderBy('bpin'),
            'usuarios' => fn ($query) => $query->orderBy('name'),
        ]);

        $metasResultado = $dependencia->metasProducto
            ->groupBy(fn ($metaProducto): string => (string) ($metaProducto->meta_resultado_id ?? 'sin_meta_resultado'))
            ->map(function ($metasProducto) {
                $metaResultado = $metasProducto->first()->metaResultado;

                return [
                    'meta' => $metaResultado,
                    'metas_producto' => $metasProducto->values(),
                    'proyectos' => $metasProducto
                        ->flatMap->proyectos
                        ->unique('id')
                        ->sortBy('bpin')
                        ->values(),
                ];
            })
            ->sortBy(fn (array $grupo): string => (string) ($grupo['meta']?->codigo_provisional ?? 'ZZZ'))
            ->values();

        return view('intelligence.dependencias.show', [
            'dependencia' => $dependencia,
            'metasResultado' => $metasResultado,
        ]);
    }
}
