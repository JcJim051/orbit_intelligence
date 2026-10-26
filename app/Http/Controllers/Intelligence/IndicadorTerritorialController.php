<?php

namespace App\Http\Controllers\Intelligence;

use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\IndicadorTerritorial;
use App\Models\IndicadorTerritorialAsignacion;
use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IndicadorTerritorialController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $query = IndicadorTerritorial::query()
            ->visiblePara($request->user())
            ->with(['dependencia:id,codigo,nombre', 'asignado:id,name,email'])
            ->orderBy('codigo');

        if ($request->filled('estado') && array_key_exists((string) $request->query('estado'), IndicadorTerritorial::ESTADOS)) {
            $query->where('estado', $request->query('estado'));
        }

        if ($request->filled('grupo')) {
            $query->where('grupo_asignacion', $request->query('grupo'));
        }

        if ($request->filled('asignado_a') && $request->user()->canCoordinateTerritorialIndicators()) {
            $request->query('asignado_a') === 'sin_asignar'
                ? $query->whereNull('asignado_a')
                : $query->where('asignado_a', (int) $request->query('asignado_a'));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(fn ($query) => $query
                ->where('codigo', 'like', $like)
                ->orWhere('nombre', 'like', $like)
                ->orWhere('dependencia_fuente', 'like', $like)
                ->orWhere('area_tematica', 'like', $like)
                ->orWhereHas('dependencia', fn ($query) => $query->where('nombre', 'like', $like)));
        }

        $summary = IndicadorTerritorial::query()
            ->visiblePara($request->user())
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('intelligence.indicadores-territoriales.index', [
            'indicadores' => $query->paginate(40)->withQueryString(),
            'summary' => $summary,
            'estados' => IndicadorTerritorial::ESTADOS,
            'grupos' => IndicadorTerritorial::query()->distinct()->orderBy('grupo_asignacion')->pluck('grupo_asignacion'),
            'revisores' => $request->user()->canCoordinateTerritorialIndicators() ? $this->revisores() : collect(),
            'canAssign' => $request->user()->canCoordinateTerritorialIndicators(),
            'canManage' => $request->user()->canCoordinateTerritorialIndicators(),
            'filters' => $request->only(['q', 'estado', 'grupo', 'asignado_a']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeCoordinator($request);

        return view('intelligence.indicadores-territoriales.create', [
            'periodicidades' => IndicadorTerritorial::PERIODICIDADES,
            'unidadesMedida' => IndicadorTerritorial::UNIDADES_MEDIDA,
            'dependencias' => Dependencia::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'revisores' => $this->revisores(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCoordinator($request);
        $data = $request->validate($this->createRules());

        $indicador = DB::transaction(function () use ($request, $data): IndicadorTerritorial {
            $indicador = IndicadorTerritorial::query()->create([
                ...collect($data)->except('asignado_a')->all(),
                'asignado_a' => $data['asignado_a'] ?? null,
                'dependencia_fuente' => isset($data['dependencia_id'])
                    ? Dependencia::query()->whereKey($data['dependencia_id'])->value('nombre')
                    : null,
                'periodicidad_original' => $data['periodicidad_original'] ?? ($data['periodicidad'] ?? null),
                'grupo_asignacion' => 'sin_asignar',
                'estado' => 'precargado',
                'fuente_importacion' => 'Creación manual',
            ]);

            if ($indicador->asignado_a) {
                IndicadorTerritorialAsignacion::query()->create([
                    'indicador_territorial_id' => $indicador->id,
                    'asignado_a' => $indicador->asignado_a,
                    'asignado_por' => $request->user()->id,
                    'grupo_origen' => 'sin_asignar',
                    'motivo' => 'Asignación durante la creación manual',
                    'asignado_at' => now(),
                ]);
            }

            return $indicador;
        });

        return $this->toIndicator($indicador)->with('status', 'Indicador territorial creado. Complete y apruebe su ficha técnica antes de cargar la serie histórica.');
    }

    public function show(Request $request, IndicadorTerritorial $indicador): View
    {
        $this->authorizeIndicator($request, $indicador);
        $indicador->load([
            'dependencia', 'asignado', 'aprobador', 'asignaciones.asignado', 'asignaciones.asignador',
            'datos.reportadoPor:id,name', 'datos.validadoPor:id,name',
        ]);

        $approved = $indicador->datos->where('estado', 'aprobado')->values();
        $pendingCount = $indicador->datos->where('estado', 'pendiente')->count();

        return view('intelligence.indicadores-territoriales.show', [
            'indicador' => $indicador,
            'estados' => IndicadorTerritorial::ESTADOS,
            'periodicidades' => IndicadorTerritorial::PERIODICIDADES,
            'canEdit' => $request->user()->canEditTerritorialIndicator($indicador),
            'canReview' => $request->user()->canReviewTerritorialIndicator($indicador),
            'canCoordinate' => $request->user()->canCoordinateTerritorialIndicators(),
            'canApproveTechnical' => ($request->user()->canCoordinateTerritorialIndicators() || $request->user()->canReviewTerritorialIndicator($indicador))
                && in_array($indicador->estado, ['precargado', 'revision_tecnica'], true),
            'canUploadHistory' => $request->user()->canEditTerritorialIndicator($indicador)
                && ! in_array($indicador->estado, ['precargado', 'revision_tecnica'], true),
            'canValidateHistory' => ($request->user()->canCoordinateTerritorialIndicators() || $request->user()->canReviewTerritorialIndicator($indicador))
                && $pendingCount > 0,
            'historySummary' => [
                'total' => $indicador->datos->count(),
                'approved' => $approved->count(),
                'pending' => $pendingCount,
                'first' => $approved->first(),
                'latest' => $approved->last(),
            ],
        ]);
    }

    public function edit(Request $request, IndicadorTerritorial $indicador): View
    {
        $this->authorizeEdit($request, $indicador);
        $indicador->load(['dependencia', 'asignado', 'aprobador']);

        return view('intelligence.indicadores-territoriales.edit', [
            'indicador' => $indicador,
            'estados' => IndicadorTerritorial::ESTADOS,
            'periodicidades' => IndicadorTerritorial::PERIODICIDADES,
            'unidadesMedida' => IndicadorTerritorial::UNIDADES_MEDIDA,
            'canReview' => $request->user()->canCoordinateTerritorialIndicators() || $request->user()->canReviewTerritorialIndicator($indicador),
        ]);
    }

    public function update(Request $request, IndicadorTerritorial $indicador): RedirectResponse
    {
        $this->authorizeEdit($request, $indicador);

        $data = $request->validate($this->technicalRules());
        $data['estado'] = 'revision_tecnica';
        $data['aprobado_por'] = null;
        $data['aprobado_at'] = null;
        $indicador->update($data);

        return $this->toIndicator($indicador)->with('status', 'Ficha técnica guardada. El indicador continúa en revisión técnica.');
    }

    public function approve(Request $request, IndicadorTerritorial $indicador): RedirectResponse
    {
        $this->authorizeReview($request, $indicador);

        $rules = $this->technicalRules(true);
        $payload = $request->has('nombre')
            ? $request->all()
            : $indicador->only(array_keys($rules));
        $data = validator($payload, $rules, [
            'definicion.required' => 'Complete la definición antes de aprobar la ficha.',
            'unidad_medida.required' => 'Complete la unidad de medida antes de aprobar la ficha.',
            'formula_calculo.required' => 'Complete la fórmula o regla de cálculo antes de aprobar la ficha.',
            'periodicidad.required' => 'Seleccione la periodicidad normalizada antes de aprobar la ficha.',
            'fuente_informacion.required' => 'Complete la fuente de información antes de aprobar la ficha.',
            'responsable_informacion.required' => 'Complete el responsable de la información antes de aprobar la ficha.',
        ])->validate();

        $indicador->update([
            ...$data,
            'estado' => 'pendiente_historico',
            'aprobado_por' => $request->user()->id,
            'aprobado_at' => now(),
        ]);

        return $this->toIndicator($indicador)->with('status', 'Ficha técnica aprobada. El indicador quedó pendiente de cargar y validar su serie histórica.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($request->user()->canCoordinateTerritorialIndicators(), 403);

        $data = $request->validate([
            'asignado_a' => ['required', 'integer', 'exists:users,id'],
            'indicator_ids' => ['nullable', 'array'],
            'indicator_ids.*' => ['integer', 'distinct', 'exists:indicadores_territoriales,id'],
            'grupo' => ['nullable', Rule::in(['verde', 'amarillo', 'blanco', 'sin_asignar'])],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);

        $reviewer = User::query()->whereKey($data['asignado_a'])->where('active', true)->firstOrFail();
        abort_unless($reviewer->role === UserRole::InformationReviewer, 422, 'El responsable debe tener el rol Enlace de información.');

        $ids = collect($data['indicator_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        if ($ids->isEmpty() && ! empty($data['grupo'])) {
            $ids = IndicadorTerritorial::query()->where('grupo_asignacion', $data['grupo'])->pluck('id');
        }

        if ($ids->isEmpty()) {
            return back()->withErrors(['indicator_ids' => 'Seleccione indicadores o indique un grupo de origen.']);
        }

        DB::transaction(function () use ($ids, $reviewer, $request, $data): void {
            IndicadorTerritorial::query()->whereKey($ids)->get()->each(function (IndicadorTerritorial $indicador) use ($reviewer, $request, $data): void {
                if ((int) $indicador->asignado_a === (int) $reviewer->id) {
                    return;
                }

                $indicador->update(['asignado_a' => $reviewer->id]);
                IndicadorTerritorialAsignacion::query()->create([
                    'indicador_territorial_id' => $indicador->id,
                    'asignado_a' => $reviewer->id,
                    'asignado_por' => $request->user()->id,
                    'grupo_origen' => $indicador->grupo_asignacion,
                    'motivo' => $data['motivo'] ?? null,
                    'asignado_at' => now(),
                ]);
            });
        });

        return back()->with('status', $ids->count().' indicador(es) asignados a '.$reviewer->name.'.');
    }

    public function destroy(Request $request, IndicadorTerritorial $indicador): RedirectResponse
    {
        $this->authorizeCoordinator($request);
        $code = $indicador->codigo;
        $indicador->delete();

        return redirect()->route('intelligence.indicadores-territoriales.index')
            ->with('status', "El indicador {$code} fue retirado del módulo. Su ficha y su serie histórica se conservaron para auditoría.");
    }

    /** @return array<string, mixed> */
    private function technicalRules(bool $required = false): array
    {
        $presence = $required ? ['required'] : ['nullable'];

        return [
            'nombre' => ['required', 'string', 'max:3000'],
            'definicion' => [...$presence, 'string'],
            'objetivo' => ['nullable', 'string'],
            'area_tematica' => ['nullable', 'string', 'max:120'],
            'tipo_indicador' => ['nullable', 'string', 'max:120'],
            'unidad_medida' => [...$presence, 'string', 'max:160'],
            'formula_calculo' => [...$presence, 'string'],
            'variables_calculo' => ['nullable', 'string'],
            'periodicidad_original' => ['nullable', 'string', 'max:160'],
            'periodicidad' => [...$presence, Rule::in(array_keys(IndicadorTerritorial::PERIODICIDADES))],
            'fuente_informacion' => [...$presence, 'string'],
            'responsable_informacion' => [...$presence, 'string'],
            'desagregaciones' => ['nullable', 'string'],
            'cobertura_geografica' => ['nullable', 'string'],
            'observaciones_tecnicas' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, mixed> */
    private function createRules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:80', Rule::unique('indicadores_territoriales', 'codigo')],
            'nombre' => ['required', 'string', 'max:3000'],
            'dependencia_id' => ['nullable', 'integer', 'exists:dependencias,id'],
            'asignado_a' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', UserRole::InformationReviewer->value)->where('active', true)],
            'area_tematica' => ['nullable', 'string', 'max:120'],
            'tipo_indicador' => ['nullable', 'string', 'max:120'],
            'unidad_medida' => ['nullable', 'string', 'max:160'],
            'periodicidad_original' => ['nullable', 'string', 'max:160'],
            'periodicidad' => ['nullable', Rule::in(array_keys(IndicadorTerritorial::PERIODICIDADES))],
            'definicion' => ['nullable', 'string'],
            'objetivo' => ['nullable', 'string'],
            'formula_calculo' => ['nullable', 'string'],
            'variables_calculo' => ['nullable', 'string'],
            'fuente_informacion' => ['nullable', 'string'],
            'responsable_informacion' => ['nullable', 'string'],
            'desagregaciones' => ['nullable', 'string'],
            'cobertura_geografica' => ['nullable', 'string'],
            'observaciones_tecnicas' => ['nullable', 'string'],
        ];
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->canAccessTerritorialIndicators(), 403);
    }

    private function authorizeCoordinator(Request $request): void
    {
        $this->authorizeAccess($request);
        abort_unless($request->user()->canCoordinateTerritorialIndicators(), 403);
    }

    private function authorizeIndicator(Request $request, IndicadorTerritorial $indicador): void
    {
        $this->authorizeAccess($request);
        abort_unless($request->user()->canCoordinateTerritorialIndicators() || (int) $indicador->asignado_a === (int) $request->user()->id, 403);
    }

    private function authorizeReview(Request $request, IndicadorTerritorial $indicador): void
    {
        $this->authorizeIndicator($request, $indicador);
        abort_unless($request->user()->canCoordinateTerritorialIndicators() || $request->user()->canReviewTerritorialIndicator($indicador), 403);
    }

    private function authorizeEdit(Request $request, IndicadorTerritorial $indicador): void
    {
        $this->authorizeIndicator($request, $indicador);
        abort_unless($request->user()->canEditTerritorialIndicator($indicador), 403);
    }

    private function revisores()
    {
        return User::query()->where('active', true)->where('role', UserRole::InformationReviewer->value)->orderBy('name')->get(['id', 'name', 'email']);
    }

    private function toIndicator(IndicadorTerritorial $indicador): RedirectResponse
    {
        return redirect()->to(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $indicador->getRouteKey()]));
    }
}
