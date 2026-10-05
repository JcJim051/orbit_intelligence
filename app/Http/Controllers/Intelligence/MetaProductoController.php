<?php

namespace App\Http\Controllers\Intelligence;

use App\Models\Actividad;
use App\Models\MetaProducto;
use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\MetaProductoCatalog;
use App\Services\Intelligence\MetaProductoProyectoWorkbook;
use App\Services\Intelligence\MetasProducto\MetaProductoAvance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MetaProductoController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new MetaProductoCatalog;
    }

    public function projectTemplate(MetaProductoProyectoWorkbook $workbook): BinaryFileResponse
    {
        $this->authorize('import', MetaProducto::class);

        $path = storage_path('app/plantilla-relacion-meta-producto-bpin-'.uniqid().'.xlsx');
        $workbook->writeTemplate($path);

        return response()
            ->download($path, 'plantilla-relacion-meta-producto-bpin.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function importProjects(Request $request, MetaProductoProyectoWorkbook $workbook): RedirectResponse
    {
        $this->authorize('import', MetaProducto::class);

        $request->validate([
            'archivo_relaciones' => ['required', 'file', 'extensions:xlsx', 'max:10240'],
        ], [
            'archivo_relaciones.required' => 'Seleccione la plantilla diligenciada.',
            'archivo_relaciones.extensions' => 'El archivo debe ser un libro .xlsx.',
            'archivo_relaciones.max' => 'El archivo no puede superar 10 MB.',
        ]);

        $result = $workbook->import($request->file('archivo_relaciones')->getPathname());

        if ($result['errores'] !== []) {
            return back()->withErrors([
                'relaciones' => 'Se encontraron errores en la carga. Corrija el archivo y vuelva a cargarlo.',
                ...collect($result['errores'])
                    ->take(30)
                    ->mapWithKeys(fn (string $error, int $index): array => ['relacion_'.$index => $error])
                    ->all(),
            ]);
        }

        return back()->with('status', sprintf(
            'Relación Meta producto ↔ BPIN actualizada: %d nuevas, %d ya existían y %d proyectos creados.',
            $result['vinculadas'],
            $result['existentes'],
            $result['proyectos_creados'],
        ));
    }

    public function show(MetaProducto $metaProducto, MetaProductoAvance $avance): View
    {
        $this->authorize('view', $metaProducto);

        $metaProducto->load([
            'metaResultado.indicador',
            'metaResultado.indicador.odsReview.assignedUser',
            'metaResultado.indicador.odsReview.reviewer',
            'metaResultado.indicador.odsReview.links.odsIndicator.target.goal',
            'metaResultado.indicador.odsReview.links.creator',
            'metaResultado.indicador.odsReview.links.reviewer',
            'metaResultado.programa.linea.eje.pilar',
            'metaResultado.subprograma.programa.linea.eje.pilar',
            'subprograma.programa.linea.eje.pilar',
            'sectorMga',
            'dependencia',
            'proyectos' => fn ($query) => $query->with(['dependencias'])->orderBy('bpin'),
        ]);

        return view('intelligence.metas-producto.show', [
            'meta' => $metaProducto,
            'avanceActual' => $avance->resumenActual($metaProducto),
            'historico' => $avance->historico($metaProducto),
            'sectoresAsociados' => $this->sectoresAsociados($metaProducto),
        ]);
    }

    private function sectoresAsociados(MetaProducto $meta): Collection
    {
        $desdeMeta = collect([$meta->dependencia])->filter();
        $desdeProyectos = $meta->proyectos->flatMap->dependencias->filter();
        $desdeActividades = Actividad::query()
            ->withoutGlobalScopes()
            ->where('meta_producto_id', $meta->id)
            ->with(['dependencia' => fn ($query) => $query->withTrashed()])
            ->get()
            ->pluck('dependencia')
            ->filter();

        return $desdeMeta
            ->merge($desdeProyectos)
            ->merge($desdeActividades)
            ->unique('id')
            ->sortBy(fn ($dependencia): string => (string) ($dependencia->codigo ?? $dependencia->nombre))
            ->values();
    }
}
