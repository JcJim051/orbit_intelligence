<?php

namespace App\Http\Controllers\Intelligence;

use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\IndicadorResultado;
use App\Models\IndicadorTerritorial;
use App\Models\IndicadorTerritorialAsignacion;
use App\Models\MetaProducto;
use App\Models\User;
use App\Services\Intelligence\IndicadoresTerritoriales\ConfiguracionReporteIndicadores;
use App\Services\Intelligence\IndicadoresTerritoriales\GeneradorCodigoIndicadorTerritorial;
use App\Services\Intelligence\IndicadoresTerritoriales\GeneradorFichaTecnicaIndicador;
use App\Services\Siid\TableroEmbedValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IndicadorTerritorialController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $query = IndicadorTerritorial::query()
            ->visiblePara($request->user())
            ->with(['dependencia:id,codigo,nombre', 'asignado:id,name,email', 'revisores:id,name,email'])
            ->orderBy('codigo');

        $summary = IndicadorTerritorial::query()
            ->visiblePara($request->user())
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        // La matriz es pequeña y ya debe cargarse completa para la tabla
        // dinámica; reutilizamos esa colección para no repetir la consulta.
        $indicadores = $query->get();
        $dependencyCount = $indicadores
            ->pluck('dependencia_id')
            ->filter()
            ->unique()
            ->count();

        return view('intelligence.indicadores-territoriales.index', [
            // La matriz PET tiene un volumen acotado. Se entrega completa para
            // que la tabla pueda buscar y paginar en el navegador sin recargas.
            'indicadores' => $indicadores,
            'dependencyCount' => $dependencyCount,
            'summary' => $summary,
            'estados' => IndicadorTerritorial::ESTADOS,
            'grupos' => IndicadorTerritorial::query()->distinct()->orderBy('grupo_asignacion')->pluck('grupo_asignacion'),
            'revisores' => $request->user()->canCoordinateTerritorialIndicators() ? $this->revisores() : collect(),
            'canAssign' => $request->user()->canCoordinateTerritorialIndicators(),
            'canManage' => $request->user()->canCoordinateTerritorialIndicators(),
            'canCreate' => $this->canCreate($request),
            'validatorEditingActive' => app(ConfiguracionReporteIndicadores::class)->edicionValidadoresActiva(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeCreator($request);
        $selfAssigned = $request->user()->isInformationReviewer();

        return view('intelligence.indicadores-territoriales.create', [
            'periodicidades' => IndicadorTerritorial::PERIODICIDADES,
            'unidadesMedida' => IndicadorTerritorial::UNIDADES_MEDIDA,
            'areasTematicas' => $this->opcionesCampo('area_tematica'),
            'tiposIndicador' => $this->opcionesCampo('tipo_indicador'),
            'coberturasGeograficas' => $this->opcionesCampo('cobertura_geografica'),
            'dependencias' => Dependencia::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'revisores' => $selfAssigned ? collect([$request->user()]) : $this->revisores(),
            'selfAssigned' => $selfAssigned,
            'metasProducto' => MetaProducto::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'indicadoresResultado' => IndicadorResultado::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function store(Request $request, GeneradorCodigoIndicadorTerritorial $generadorCodigo): RedirectResponse
    {
        $this->authorizeCreator($request);
        $data = $request->validate($this->createRules());
        if ($request->user()->isInformationReviewer()) {
            $data['asignado_a'] = $request->user()->id;
        }
        $this->validateDashboard($data);
        [$data] = $this->prepareTechnicalPdf($request, null, $data);

        $indicador = DB::transaction(function () use ($request, $data, $generadorCodigo): IndicadorTerritorial {
            $indicador = IndicadorTerritorial::query()->create([
                ...collect($data)->except('asignado_a')->all(),
                'codigo' => $generadorCodigo->siguiente(),
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
                $indicador->revisores()->syncWithoutDetaching([
                    $indicador->asignado_a => [
                        'asignado_por' => $request->user()->id,
                        'motivo' => 'Asignación durante la creación manual',
                    ],
                ]);

                IndicadorTerritorialAsignacion::query()->create([
                    'indicador_territorial_id' => $indicador->id,
                    'asignado_a' => $indicador->asignado_a,
                    'asignado_por' => $request->user()->id,
                    'grupo_origen' => 'sin_asignar',
                    'motivo' => 'Asignación durante la creación manual',
                    'asignado_at' => now(),
                ]);
            }

            if (DB::getDriverName() === 'pgsql') {
                $indicador->publicacion()->firstOrCreate([], ['visible' => false]);
            }

            return $indicador;
        });

        return $this->toIndicator($indicador)->with('status', 'Indicador territorial creado. Complete y apruebe su ficha técnica antes de cargar la serie histórica.');
    }

    public function show(Request $request, IndicadorTerritorial $indicador): View
    {
        $this->authorizeIndicator($request, $indicador);
        $indicador->load([
            'dependencia', 'asignado', 'revisores', 'aprobador', 'asignaciones.asignado', 'asignaciones.asignador',
            'datos.reportadoPor:id,name', 'datos.validadoPor:id,name',
        ]);

        $approved = $indicador->datos->where('estado', 'aprobado')->values();
        $pendingCount = $indicador->datos->where('estado', 'pendiente')->count();
        $pendingHistoryCount = $indicador->datos
            ->where('estado', 'pendiente')
            ->where('origen', '!=', 'reporte_mensual')
            ->count();
        $canReviewPeriodic = $request->user()->canCoordinateTerritorialIndicators()
            || $request->user()->canReviewTerritorialIndicator($indicador);
        $canEdit = $this->canEdit($request, $indicador);

        return view('intelligence.indicadores-territoriales.show', [
            'indicador' => $indicador,
            'estados' => IndicadorTerritorial::ESTADOS,
            'periodicidades' => IndicadorTerritorial::PERIODICIDADES,
            'canEdit' => $canEdit,
            'canReview' => $request->user()->canReviewTerritorialIndicator($indicador),
            'canCoordinate' => $request->user()->canCoordinateTerritorialIndicators(),
            'canReviewPeriodic' => $canReviewPeriodic,
            'canApproveTechnical' => ($request->user()->canCoordinateTerritorialIndicators() || $request->user()->canReviewTerritorialIndicator($indicador))
                && in_array($indicador->estado, ['precargado', 'revision_tecnica'], true),
            'canDownloadTechnicalSheet' => filled($indicador->aprobado_at) && filled($indicador->aprobado_por),
            'canUploadHistory' => $canEdit
                && ! in_array($indicador->estado, ['precargado', 'revision_tecnica'], true),
            'canValidateHistory' => ($request->user()->canCoordinateTerritorialIndicators() || $request->user()->canReviewTerritorialIndicator($indicador))
                && $pendingHistoryCount > 0,
            'historySummary' => [
                'total' => $indicador->datos->count(),
                'approved' => $approved->count(),
                'pending' => $pendingCount,
                'first' => $approved->first(),
                'latest' => $approved->last(),
            ],
        ]);
    }

    public function downloadTechnicalSheet(
        Request $request,
        IndicadorTerritorial $indicador,
        GeneradorFichaTecnicaIndicador $generator,
    ): BinaryFileResponse {
        $this->authorizeIndicator($request, $indicador);
        abort_unless(filled($indicador->aprobado_at) && filled($indicador->aprobado_por), 422, 'La ficha técnica debe estar validada antes de generar el documento.');

        $path = $generator->generate($indicador);
        $filename = 'ficha-tecnica-'.Str::slug($indicador->codigo).'.docx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
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
            'areasTematicas' => $this->opcionesCampo('area_tematica', $indicador->area_tematica),
            'tiposIndicador' => $this->opcionesCampo('tipo_indicador', $indicador->tipo_indicador),
            'coberturasGeograficas' => $this->opcionesCampo('cobertura_geografica', $indicador->cobertura_geografica),
            'dependencias' => Dependencia::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'canReview' => $request->user()->canCoordinateTerritorialIndicators() || $request->user()->canReviewTerritorialIndicator($indicador),
            'metasProducto' => MetaProducto::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'indicadoresResultado' => IndicadorResultado::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function update(Request $request, IndicadorTerritorial $indicador): RedirectResponse
    {
        $this->authorizeEdit($request, $indicador);

        $data = $request->validate($this->technicalRules(true));
        $this->validateDashboard($data);
        [$data, $previousPdf] = $this->prepareTechnicalPdf($request, $indicador, $data);
        $data['dependencia_fuente'] = Dependencia::query()->whereKey($data['dependencia_id'])->value('nombre');
        $data['estado'] = 'revision_tecnica';
        $data['aprobado_por'] = null;
        $data['aprobado_at'] = null;
        $indicador->update($data);
        if ($previousPdf) {
            Storage::disk('public')->delete($previousPdf);
        }

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
        $this->validateDashboard($data);
        [$data, $previousPdf] = $this->prepareTechnicalPdf($request, $indicador, $data);

        $tieneHistorico = $indicador->datos()->exists();
        $historicoTienePendientes = $indicador->datos()->where('estado', '!=', 'aprobado')->exists();

        $indicador->update([
            ...$data,
            'estado' => $tieneHistorico && ! $historicoTienePendientes ? 'historico_validado' : 'pendiente_historico',
            'aprobado_por' => $request->user()->id,
            'aprobado_at' => now(),
        ]);
        if ($previousPdf) {
            Storage::disk('public')->delete($previousPdf);
        }

        $mensaje = $tieneHistorico && ! $historicoTienePendientes
            ? 'Ficha técnica aprobada. La serie histórica ya estaba validada y el indicador quedó habilitado para seguimiento periódico.'
            : 'Ficha técnica aprobada. El indicador quedó pendiente de cargar y validar su serie histórica.';

        return $this->toIndicator($indicador)->with('status', $mensaje);
    }

    public function uploadTechnicalPdf(Request $request, IndicadorTerritorial $indicador): RedirectResponse
    {
        $this->authorizeEdit($request, $indicador);

        if (! Schema::hasColumn('indicadores_territoriales', 'ficha_tecnica_pdf_path')) {
            return back()->withErrors([
                'ficha_tecnica_pdf' => 'La actualización de base de datos para la ficha definitiva todavía no está instalada.',
            ])->withFragment('cargar-ficha-definitiva');
        }

        $data = $request->validate([
            'ficha_tecnica_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'ficha_tecnica_pdf.required' => 'Seleccione el archivo PDF que desea publicar.',
            'ficha_tecnica_pdf.file' => 'La ficha seleccionada no se pudo recibir como archivo. Verifique el límite de carga del servidor.',
            'ficha_tecnica_pdf.mimes' => 'La ficha técnica definitiva debe ser un archivo PDF.',
            'ficha_tecnica_pdf.max' => 'La ficha técnica definitiva no puede superar los 10 MB.',
        ]);

        $previous = $indicador->ficha_tecnica_pdf_path;
        $path = null;

        try {
            $path = $data['ficha_tecnica_pdf']->store('indicadores-territoriales/fichas-tecnicas', 'public');
            if (! $path) {
                throw new \RuntimeException('El disco público no devolvió una ruta para el archivo.');
            }

            $indicador->updateOrFail(['ficha_tecnica_pdf_path' => $path]);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            report($exception);

            return back()->withErrors([
                'ficha_tecnica_pdf' => 'No fue posible guardar el PDF. Verifique que storage/app/public tenga permisos de escritura.',
            ])->withFragment('cargar-ficha-definitiva');
        }

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return $this->toIndicator($indicador)->with('status', 'Ficha técnica definitiva cargada y publicada correctamente.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($request->user()->canCoordinateTerritorialIndicators(), 403);

        $data = $request->validate([
            'asignados_a' => ['required', 'array', 'min:1'],
            'asignados_a.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'indicator_ids' => ['nullable', 'array'],
            'indicator_ids.*' => ['integer', 'distinct', 'exists:indicadores_territoriales,id'],
            'grupo' => ['nullable', Rule::in(['verde', 'amarillo', 'blanco', 'sin_asignar'])],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);

        $reviewerIds = collect($data['asignados_a'])->map(fn ($id): int => (int) $id)->unique()->values();
        $reviewers = User::query()
            ->whereKey($reviewerIds)
            ->where('active', true)
            ->where('role', UserRole::InformationReviewer->value)
            ->orderBy('name')
            ->get();

        if ($reviewers->count() !== $reviewerIds->count()) {
            throw ValidationException::withMessages([
                'asignados_a' => 'Todos los responsables deben ser usuarios activos con rol Validador de información.',
            ]);
        }

        $ids = collect($data['indicator_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        if ($ids->isEmpty() && ! empty($data['grupo'])) {
            $ids = IndicadorTerritorial::query()->where('grupo_asignacion', $data['grupo'])->pluck('id');
        }

        if ($ids->isEmpty()) {
            return back()->withErrors(['indicator_ids' => 'Seleccione indicadores o indique un grupo de origen.']);
        }

        DB::transaction(function () use ($ids, $reviewers, $request, $data): void {
            $pivot = $reviewers->mapWithKeys(fn (User $reviewer): array => [
                $reviewer->id => [
                    'asignado_por' => $request->user()->id,
                    'motivo' => $data['motivo'] ?? null,
                ],
            ])->all();

            IndicadorTerritorial::query()->whereKey($ids)->get()->each(function (IndicadorTerritorial $indicador) use ($reviewers, $request, $data, $pivot): void {
                $indicador->revisores()->sync($pivot);
                $indicador->update(['asignado_a' => $reviewers->first()->id]);

                $reviewers->each(function (User $reviewer) use ($indicador, $request, $data): void {
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
        });

        return back()->with('status', $ids->count().' indicador(es) asignados a '.$reviewers->pluck('name')->join(', ').'.');
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
            'convencion' => ['nullable', 'string'],
            'definicion' => [...$presence, 'string'],
            'objetivo' => [...$presence, 'string'],
            'dependencia_id' => [...$presence, 'integer', 'exists:dependencias,id'],
            'area_tematica' => [...$presence, 'string', 'max:120'],
            'tipo_indicador' => [...$presence, 'string', 'max:120'],
            'unidad_medida' => [...$presence, 'string', 'max:160'],
            'sentido_deseado' => [...$presence, Rule::in(['aumentar', 'disminuir', 'mantener'])],
            'meta_producto_id' => ['nullable', 'integer', 'exists:metas_producto,id'],
            'indicador_resultado_id' => ['nullable', 'integer', 'exists:indicadores_resultado,id'],
            'formula_calculo' => [...$presence, 'string'],
            'variables_calculo' => ['nullable', 'string'],
            'periodicidad_original' => ['nullable', 'string', 'max:160'],
            'periodicidad' => [...$presence, Rule::in(array_keys(IndicadorTerritorial::PERIODICIDADES))],
            'fuente_informacion' => [...$presence, 'string'],
            'url_ficha' => ['nullable', 'url', 'max:2000'],
            'ficha_tecnica_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'eliminar_ficha_tecnica_pdf' => ['nullable', 'boolean'],
            'tablero_tipo' => ['nullable', Rule::in(['looker', 'superset'])],
            'tablero_referencia' => ['nullable', 'string', 'max:2000'],
            'responsable_informacion' => [...$presence, 'string'],
            'desagregaciones' => ['nullable', 'string'],
            'cobertura_geografica' => [...$presence, 'string'],
            'observaciones_tecnicas' => ['nullable', 'string'],
            'limitaciones' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, mixed> */
    private function createRules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:3000'],
            'convencion' => ['nullable', 'string'],
            'dependencia_id' => ['required', 'integer', 'exists:dependencias,id'],
            'asignado_a' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', UserRole::InformationReviewer->value)->where('active', true)],
            'area_tematica' => ['required', 'string', 'max:120'],
            'tipo_indicador' => ['required', 'string', 'max:120'],
            'unidad_medida' => ['required', 'string', 'max:160'],
            'sentido_deseado' => ['required', Rule::in(['aumentar', 'disminuir', 'mantener'])],
            'meta_producto_id' => ['nullable', 'integer', 'exists:metas_producto,id'],
            'indicador_resultado_id' => ['nullable', 'integer', 'exists:indicadores_resultado,id'],
            'periodicidad_original' => ['nullable', 'string', 'max:160'],
            'periodicidad' => ['required', Rule::in(array_keys(IndicadorTerritorial::PERIODICIDADES))],
            'definicion' => ['required', 'string'],
            'objetivo' => ['required', 'string'],
            'formula_calculo' => ['required', 'string'],
            'variables_calculo' => ['nullable', 'string'],
            'fuente_informacion' => ['required', 'string'],
            'url_ficha' => ['nullable', 'url', 'max:2000'],
            'ficha_tecnica_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'tablero_tipo' => ['nullable', Rule::in(['looker', 'superset'])],
            'tablero_referencia' => ['nullable', 'string', 'max:2000'],
            'responsable_informacion' => ['required', 'string'],
            'desagregaciones' => ['nullable', 'string'],
            'cobertura_geografica' => ['required', 'string'],
            'observaciones_tecnicas' => ['nullable', 'string'],
            'limitaciones' => ['nullable', 'string'],
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

    private function authorizeCreator(Request $request): void
    {
        $this->authorizeAccess($request);
        abort_unless($this->canCreate($request), 403);
    }

    private function canCreate(Request $request): bool
    {
        if ($request->user()->canCoordinateTerritorialIndicators()) {
            return true;
        }

        return $request->user()->isInformationReviewer()
            && app(ConfiguracionReporteIndicadores::class)->edicionValidadoresActiva();
    }

    private function authorizeIndicator(Request $request, IndicadorTerritorial $indicador): void
    {
        $this->authorizeAccess($request);
        abort_unless($request->user()->canCoordinateTerritorialIndicators() || $request->user()->canReviewTerritorialIndicator($indicador), 403);
    }

    private function authorizeReview(Request $request, IndicadorTerritorial $indicador): void
    {
        $this->authorizeIndicator($request, $indicador);
        abort_unless($request->user()->canCoordinateTerritorialIndicators() || $request->user()->canReviewTerritorialIndicator($indicador), 403);
    }

    private function authorizeEdit(Request $request, IndicadorTerritorial $indicador): void
    {
        $this->authorizeIndicator($request, $indicador);
        abort_unless($this->canEdit($request, $indicador), 403);
    }

    private function canEdit(Request $request, IndicadorTerritorial $indicador): bool
    {
        if (! $request->user()->canEditTerritorialIndicator($indicador)) {
            return false;
        }

        return ! $request->user()->isInformationReviewer()
            || app(ConfiguracionReporteIndicadores::class)->edicionValidadoresActiva();
    }

    private function revisores()
    {
        return User::query()->where('active', true)->where('role', UserRole::InformationReviewer->value)->orderBy('name')->get(['id', 'name', 'email']);
    }

    /** @return array<int, string> */
    private function opcionesCampo(string $campo, ?string $actual = null): array
    {
        return IndicadorTerritorial::query()
            ->whereNotNull($campo)
            ->where($campo, '!=', '')
            ->distinct()
            ->orderBy($campo)
            ->pluck($campo)
            ->when(filled($actual), fn ($opciones) => $opciones->push($actual))
            ->map(fn ($opcion): string => trim((string) $opcion))
            ->filter()
            ->unique(fn (string $opcion): string => Str::lower($opcion))
            ->sortBy(fn (string $opcion): string => Str::lower($opcion), SORT_NATURAL)
            ->values()
            ->all();
    }

    private function toIndicator(IndicadorTerritorial $indicador): RedirectResponse
    {
        return redirect()->to(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $indicador->getRouteKey()]));
    }

    /** @param array<string, mixed> $data */
    private function validateDashboard(array $data): void
    {
        $type = $data['tablero_tipo'] ?? null;
        $reference = $data['tablero_referencia'] ?? null;
        if (! filled($type) && ! filled($reference)) {
            return;
        }

        $validator = app(TableroEmbedValidator::class);
        $valid = $type === 'looker' ? $validator->looker($reference) : ($type === 'superset' && $validator->superset($reference));
        if (! $valid) {
            throw ValidationException::withMessages([
                'tablero_referencia' => $type === 'looker'
                    ? 'Looker requiere una URL /embed/ de un dominio autorizado.'
                    : 'Superset requiere el UUID válido de un tablero embebido.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0:array<string, mixed>,1:?string}
     */
    private function prepareTechnicalPdf(Request $request, ?IndicadorTerritorial $indicator, array $data): array
    {
        unset($data['ficha_tecnica_pdf'], $data['eliminar_ficha_tecnica_pdf']);

        $previous = $indicator?->ficha_tecnica_pdf_path;
        $replace = $request->hasFile('ficha_tecnica_pdf');
        $remove = $request->boolean('eliminar_ficha_tecnica_pdf');

        if ($replace) {
            $data['ficha_tecnica_pdf_path'] = $request->file('ficha_tecnica_pdf')->store(
                'indicadores-territoriales/fichas-tecnicas',
                'public',
            );

            return [$data, $previous];
        }

        if ($remove && $previous) {
            $data['ficha_tecnica_pdf_path'] = null;

            return [$data, $previous];
        }

        return [$data, null];
    }
}
