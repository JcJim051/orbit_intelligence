<?php

namespace App\Http\Controllers\Intelligence;

use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\MetaProducto;
use App\Models\PlanIndicativoMeta;
use App\Services\Intelligence\PlanIndicativoWorkbook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlanIndicativoController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->canAccessManagementGoals() ?? false, 403);

        $vigencia = $this->vigencia($request);
        if (! Schema::hasTable('plan_indicativo_metas')) {
            return view('intelligence.plan-indicativo.index', [
                'vigencia' => $vigencia,
                'vigenciaSiguiente' => $vigencia + 1,
                'metas' => collect(),
                'total' => 0.0,
                'programadas' => 0,
                'canEdit' => false,
                'workspaceUrl' => Workspace::getUrl(['workspace' => 'plan-indicativo']),
                'requiresMigration' => true,
            ]);
        }

        $metas = MetaProducto::query()
            ->withoutGlobalScopes()
            ->with([
                'subprograma.programa.linea.eje.pilar',
                'dependencia',
                'planIndicativo' => fn ($query) => $query->where('vigencia', $vigencia),
            ])
            ->orderBy('codigo')
            ->get();

        $total = (float) $metas->sum(fn (MetaProducto $meta): float => (float) ($meta->planIndicativo->first()?->valor_programado ?? 0));
        $programadas = $metas->filter(fn (MetaProducto $meta): bool => (float) ($meta->planIndicativo->first()?->valor_programado ?? 0) > 0)->count();

        return view('intelligence.plan-indicativo.index', [
            'vigencia' => $vigencia,
            'vigenciaSiguiente' => $vigencia + 1,
            'metas' => $metas,
            'total' => $total,
            'programadas' => $programadas,
            'canEdit' => $request->user()?->canManageIntelligenceCatalogs() ?? false,
            'workspaceUrl' => Workspace::getUrl(['workspace' => 'plan-indicativo']),
            'requiresMigration' => false,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManageIntelligenceCatalogs() ?? false, 403);

        $validated = $request->validate([
            'vigencia' => ['required', 'integer', 'between:2024,2035'],
            'plan' => ['array'],
            'plan.*.meta_producto_id' => ['required', 'integer', 'exists:metas_producto,id'],
            'plan.*.valor_programado' => ['nullable', 'numeric', 'min:0'],
            'plan.*.observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = 0;

        DB::transaction(function () use ($validated, $request, &$updated): void {
            foreach ($validated['plan'] ?? [] as $row) {
                $plan = PlanIndicativoMeta::query()->updateOrCreate(
                    [
                        'meta_producto_id' => (int) $row['meta_producto_id'],
                        'vigencia' => (int) $validated['vigencia'],
                    ],
                    [
                        'valor_programado' => (float) ($row['valor_programado'] ?? 0),
                        'observacion' => trim((string) ($row['observacion'] ?? '')) ?: null,
                        'updated_by' => $request->user()->id,
                    ],
                );

                if ($plan->wasRecentlyCreated || $plan->wasChanged()) {
                    $updated++;
                }
            }
        });

        return redirect()
            ->to(Workspace::getUrl(['workspace' => 'plan-indicativo', 'vigencia' => $validated['vigencia']]))
            ->with('status', "Plan indicativo actualizado: {$updated} fila(s) modificada(s).");
    }

    public function template(Request $request, PlanIndicativoWorkbook $workbook): BinaryFileResponse
    {
        abort_unless($request->user()?->canManageIntelligenceCatalogs() ?? false, 403);

        $vigencia = $this->vigencia($request);
        $path = storage_path('app/plantilla-plan-indicativo-'.$vigencia.'-'.uniqid().'.xlsx');
        $workbook->writeTemplate($path, $vigencia);

        return response()
            ->download($path, "plantilla-plan-indicativo-{$vigencia}.xlsx")
            ->deleteFileAfterSend(true);
    }

    public function import(Request $request, PlanIndicativoWorkbook $workbook): RedirectResponse
    {
        abort_unless($request->user()?->canManageIntelligenceCatalogs() ?? false, 403);

        $validated = $request->validate([
            'vigencia' => ['nullable', 'integer', 'between:2024,2035'],
            'archivo_plan' => ['required', 'file', 'extensions:xlsx', 'max:10240'],
        ], [
            'archivo_plan.required' => 'Seleccione la matriz del plan indicativo.',
            'archivo_plan.extensions' => 'El archivo debe ser un libro .xlsx.',
            'archivo_plan.max' => 'El archivo no puede superar 10 MB.',
        ]);

        $result = $workbook->import(
            $request->file('archivo_plan')->getPathname(),
            isset($validated['vigencia']) ? (int) $validated['vigencia'] : null,
            $request->user(),
        );

        if ($result['errores'] !== []) {
            return back()->withErrors([
                'plan' => 'Se encontraron errores en la carga. Corrija la matriz y vuelva a cargarla.',
                ...collect($result['errores'])
                    ->take(40)
                    ->mapWithKeys(fn (string $error, int $index): array => ['plan_'.$index => $error])
                    ->all(),
            ]);
        }

        $vigencia = (int) ($validated['vigencia'] ?? now()->year);

        return redirect()
            ->to(Workspace::getUrl(['workspace' => 'plan-indicativo', 'vigencia' => $vigencia]))
            ->with('status', sprintf(
                'Plan indicativo importado: %d fila(s) leída(s), %d creada(s), %d actualizada(s), %d sin cambios.',
                $result['leidas'],
                $result['creadas'],
                $result['actualizadas'],
                $result['sin_cambios'],
            ));
    }

    private function vigencia(Request $request): int
    {
        $vigencia = $request->integer('vigencia') ?: now()->year;

        return max(2024, min(2035, $vigencia));
    }
}
