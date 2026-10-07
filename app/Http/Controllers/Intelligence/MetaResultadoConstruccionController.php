<?php

namespace App\Http\Controllers\Intelligence;

use App\Enums\OrientacionIndicador;
use App\Http\Controllers\Controller;
use App\Models\MetaProducto;
use App\Models\MetaResultado;
use App\Models\MetaResultadoConstruccion;
use App\Models\MetaResultadoConstruccionComentario;
use App\Models\User;
use App\Services\Intelligence\MetasProducto\MetaProductoAvance;
use App\Services\Ods\AssignMetaResultadoConstructionTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MetaResultadoConstruccionController extends Controller
{
    /** @var array<string, string> */
    public const STATUS = [
        'pending' => 'Pendiente',
        'in_review' => 'En revisión',
        'pending_validation' => 'En validación',
        'needs_adjustment' => 'Requiere ajuste',
        'completed' => 'Completado',
        'not_applicable' => 'No aplica',
    ];

    /** @var array<string, string> */
    public const MEASUREMENT_MODES = [
        'dual' => 'Medir gestión e indicador resultado por separado',
        'management_as_result' => 'Tomar el avance de gestión como avance de la meta resultado',
    ];

    /** @var array<string, string> */
    public const MEASUREMENT_MODE_DESCRIPTIONS = [
        'dual' => 'Úsela cuando sí sea posible medir el indicador de resultado: se conserva un dato para el avance de gestión de las metas producto y otro para la medición del resultado con línea base y valor actual.',
        'management_as_result' => 'Úsela cuando no haya una forma confiable de medir el indicador de resultado todavía: el avance de la meta resultado será el mismo avance de gestión calculado desde sus metas producto asociadas.',
    ];

    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        if (! $this->hasRequiredTables()) {
            return view('intelligence.meta-resultado-construccion.index', [
                'tasks' => collect(),
                'filters' => [
                    'q' => trim((string) $request->query('q', '')),
                    'status' => (string) $request->query('status', ''),
                    'assigned' => (string) $request->query('assigned', ''),
                ],
                'statuses' => self::STATUS,
                'reviewers' => collect(),
                'summary' => collect(),
                'canManageAssignments' => $this->canManageAssignments($request->user()),
                'requiresMigration' => true,
            ]);
        }

        $this->ensureConstructionTasks();
        $canManageAssignments = $this->canManageAssignments($request->user());

        $query = MetaResultadoConstruccion::query()
            ->with([
                'metaResultado' => fn ($query) => $query
                    ->select('id', 'codigo', 'codigo_provisional', 'descripcion', 'programa_id', 'subprograma_id', 'indicador_resultado_id')
                    ->withCount('metasProducto')
                    ->with([
                        'programa:id,numeral,nombre,linea_id',
                        'programa.linea:id,numeral,nombre,eje_id',
                        'programa.linea.eje:id,numeral,nombre,pilar_id',
                        'programa.linea.eje.pilar:id,numeral,nombre',
                        'subprograma:id,numeral,nombre,programa_id',
                        'subprograma.programa:id,numeral,nombre,linea_id',
                        'subprograma.programa.linea:id,numeral,nombre,eje_id',
                        'subprograma.programa.linea.eje:id,numeral,nombre,pilar_id',
                        'subprograma.programa.linea.eje.pilar:id,numeral,nombre',
                        'indicador:id,codigo,nombre,unidad_medida',
                    ]),
                'assignedUser',
            ])
            ->latest('updated_at');

        if ($request->user()->mustSeeOnlyAssignedOdsReviews()) {
            $query->where('assigned_to', $request->user()->id);
        }

        if ($request->filled('status') && array_key_exists((string) $request->query('status'), self::STATUS)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->user()->mustSeeOnlyAssignedOdsReviews()) {
            // Los revisores dedicados no pueden consultar tareas de otros usuarios.
        } elseif ($canManageAssignments && $request->query('assigned') === 'me') {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($canManageAssignments && ctype_digit((string) $request->query('assigned'))) {
            $query->where('assigned_to', (int) $request->query('assigned'));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = "%{$search}%";
            $normalizedStatus = collect(self::STATUS)
                ->filter(fn (string $label, string $status): bool => str_contains(mb_strtolower($label), mb_strtolower($search)) || str_contains($status, mb_strtolower($search)))
                ->keys()
                ->all();

            $query->where(function ($query) use ($like, $normalizedStatus, $canManageAssignments): void {
                $query
                    ->whereIn('status', $normalizedStatus)
                    ->orWhere('current_value_source', 'like', $like)
                    ->orWhere('methodology_notes', 'like', $like)
                    ->orWhereHas('metaResultado', fn ($query) => $query
                        ->where('descripcion', 'like', $like)
                        ->orWhere('codigo_provisional', 'like', $like)
                        ->orWhere('codigo', 'like', $like)
                        ->orWhereHas('indicador', fn ($query) => $query
                            ->where('nombre', 'like', $like)
                            ->orWhere('codigo', 'like', $like)
                            ->orWhere('unidad_medida', 'like', $like))
                        ->orWhereHas('metasProducto', fn ($query) => $query
                            ->where('nombre', 'like', $like)
                            ->orWhere('codigo', 'like', $like)));

                if ($canManageAssignments) {
                    $query->orWhereHas('assignedUser', fn ($query) => $query
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like));
                }

                $query->orWhereHas('comments', fn ($query) => $query
                        ->where('comment', 'like', $like)
                        ->orWhere('event_type', 'like', $like));
            });
        }

        return view('intelligence.meta-resultado-construccion.index', [
            'tasks' => $query->simplePaginate(50)->withQueryString(),
            'filters' => [
                'q' => $search,
                'status' => (string) $request->query('status', ''),
                'assigned' => (string) $request->query('assigned', ''),
            ],
            'statuses' => self::STATUS,
            'reviewers' => $canManageAssignments ? $this->reviewers() : collect(),
            'summary' => MetaResultadoConstruccion::query()
                ->when($request->user()->mustSeeOnlyAssignedOdsReviews(), fn ($query) => $query->where('assigned_to', $request->user()->id))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'canManageAssignments' => $canManageAssignments,
        ]);
    }

    public function assignTeam(Request $request, AssignMetaResultadoConstructionTeam $assignments): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($this->canManageAssignments($request->user()), 403);
        abort_unless($this->hasRequiredTables(), 409, 'Falta ejecutar la migración de construcción de metas resultado.');

        $validated = $request->validate([
            'reviewer_ids' => ['required', 'array', 'min:1'],
            'reviewer_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ], [
            'reviewer_ids.required' => 'Seleccione al menos un usuario para repartir pendientes.',
            'reviewer_ids.min' => 'Seleccione al menos un usuario para repartir pendientes.',
        ]);

        $reviewerIds = collect($validated['reviewer_ids'])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $selectedUsers = User::query()
            ->where('active', true)
            ->whereIn('id', $reviewerIds)
            ->get()
            ->filter(fn (User $user): bool => $user->canReviewOdsIndicators());

        if ($selectedUsers->count() !== count($reviewerIds)) {
            return back()
                ->withInput()
                ->with('error', 'Solo puede repartir pendientes entre usuarios activos con permiso para revisar ODS.');
        }

        $result = $assignments->assignPending($request->user(), $reviewerIds);
        $warnings = [];

        if ($result['missing_reviewers'] !== []) {
            $warnings[] = 'No se encontraron: '.implode(', ', $result['missing_reviewers']).'.';
        }

        if ($result['missing_validator']) {
            $warnings[] = 'No se encontró a Bibiana como validadora.';
        }

        $distribution = collect($result['distribution'])
            ->map(fn (int $total, string $name): string => "{$name}: {$total}")
            ->implode(' · ');

        $message = "Reparto de metas resultado actualizado: {$result['assigned']} tarea(s) asignada(s). Distribución actual: {$distribution}.";

        if ($warnings !== []) {
            $message .= ' '.implode(' ', $warnings);
        }

        return redirect()
            ->to(\App\Filament\Pages\Workspace::getUrl(['workspace' => 'construccion-metas-resultado']))
            ->with('status', $message);
    }

    public function show(Request $request, MetaResultadoConstruccion $task, MetaProductoAvance $avance): View
    {
        $this->authorizeAccess($request);
        abort_unless($this->hasRequiredTables(), 409, 'Falta ejecutar la migración de construcción de metas resultado.');
        abort_if($request->user()->mustSeeOnlyAssignedOdsReviews() && $task->assigned_to !== $request->user()->id, 403);

        $task->load([
            'metaResultado.programa.linea.eje.pilar',
            'metaResultado.subprograma.programa.linea.eje.pilar',
            'metaResultado.indicador.odsReview.links.odsIndicator.target.goal',
            'metaResultado.metasProducto.dependencia',
            'metaResultado.metasProducto.sectorMga',
            'assignedUser',
            'reviewer',
            'comments.user',
        ]);

        $management = $this->managementProgress($task->metaResultado, $avance);
        $resultProgress = $this->resultProgress($task, $management['percentage']);
        $reviewerLocked = $this->reviewerLocked($request, $task);

        return view('intelligence.meta-resultado-construccion.show', [
            'task' => $task,
            'statuses' => self::STATUS,
            'measurementModes' => self::MEASUREMENT_MODES,
            'measurementModeDescriptions' => self::MEASUREMENT_MODE_DESCRIPTIONS,
            'availableStatuses' => $this->availableStatusesFor($request->user(), $task),
            'reviewers' => $this->reviewers(),
            'canEdit' => ! $reviewerLocked,
            'reviewerLocked' => $reviewerLocked,
            'canManageAssignments' => $this->canManageAssignments($request->user()),
            'management' => $management,
            'resultProgress' => $resultProgress,
            'acceptedOdsLinks' => $task->metaResultado?->indicador?->odsReview?->links
                ?->where('status', 'accepted')
                ->values() ?? collect(),
        ]);
    }

    public function update(Request $request, MetaResultadoConstruccion $task, MetaProductoAvance $avance): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($this->hasRequiredTables(), 409, 'Falta ejecutar la migración de construcción de metas resultado.');
        abort_if($request->user()->mustSeeOnlyAssignedOdsReviews() && $task->assigned_to !== $request->user()->id, 403);
        abort_if($this->reviewerLocked($request, $task), 403);

        $availableStatuses = $this->availableStatusesFor($request->user(), $task);

        $rules = [
            'status' => ['required', Rule::in(array_keys($availableStatuses))],
            'measurement_mode' => ['required', Rule::in(array_keys(self::MEASUREMENT_MODES))],
            'baseline_value' => ['nullable', 'numeric'],
            'current_value' => ['nullable', 'numeric'],
            'current_value_date' => ['nullable', 'date'],
            'current_value_source' => ['nullable', 'string', 'max:4000'],
            'methodology_notes' => ['nullable', 'string', 'max:8000'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ];

        $canManageAssignments = $this->canManageAssignments($request->user());

        if ($canManageAssignments) {
            $rules['assigned_to'] = ['nullable', 'integer', 'exists:users,id'];
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($request, $task, $avance, $validated, $canManageAssignments): void {
            $old = $task->only(['assigned_to', 'status', 'measurement_mode', 'baseline_value', 'current_value']);
            $management = $this->managementProgress($task->metaResultado, $avance);

            $task->fill([
                'assigned_to' => $canManageAssignments ? ($validated['assigned_to'] ?? null) : $task->assigned_to,
                'status' => $validated['status'],
                'measurement_mode' => $validated['measurement_mode'],
                'baseline_value' => $validated['baseline_value'] ?? null,
                'current_value' => $validated['current_value'] ?? null,
                'current_value_date' => $validated['current_value_date'] ?? null,
                'current_value_source' => $validated['current_value_source'] ? trim($validated['current_value_source']) : null,
                'methodology_notes' => $validated['methodology_notes'] ? trim($validated['methodology_notes']) : null,
                'management_progress_pct' => $management['percentage'],
            ]);

            $task->result_progress_pct = $this->resultProgress($task, $management['percentage']);

            if (in_array($validated['status'], ['completed', 'not_applicable'], true)) {
                $task->reviewed_by = $request->user()->id;
                $task->reviewed_at = now();
            }

            $task->save();

            $this->comment($task, $request->user(), $validated['comment'] ?: 'Construcción de meta resultado actualizada.', 'workflow', [
                'before' => $old,
                'after' => $task->only(['assigned_to', 'status', 'measurement_mode', 'baseline_value', 'current_value', 'management_progress_pct', 'result_progress_pct']),
            ]);
        });

        return redirect($this->showUrl($task))->with('status', 'Construcción de meta resultado guardada.');
    }

    public function storeComment(Request $request, MetaResultadoConstruccion $task): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($this->hasRequiredTables(), 409, 'Falta ejecutar la migración de construcción de metas resultado.');
        abort_if($request->user()->mustSeeOnlyAssignedOdsReviews() && $task->assigned_to !== $request->user()->id, 403);
        abort_if($this->reviewerLocked($request, $task), 403);

        $validated = $request->validate(['comment' => ['required', 'string', 'max:4000']]);

        $this->comment($task, $request->user(), $validated['comment']);

        return redirect($this->showUrl($task))->with('status', 'Comentario guardado.');
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->canReviewOdsIndicators(), 403);
    }

    private function canManageAssignments(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        $role = $user->role;
        $roleName = $role instanceof \UnitEnum ? strtolower($role->name) : '';
        $roleValue = $role instanceof \BackedEnum ? strtolower((string) $role->value) : strtolower((string) $role);

        if (in_array($roleName, ['odsreviewer', 'odsvalidator'], true)
            || in_array($roleValue, ['ods_reviewer', 'ods_validator', 'ods-reviewer', 'ods-validator', 'revisor_ods', 'validador_ods'], true)) {
            return false;
        }

        if (method_exists($user, 'canManageGoalsCatalog') && $user->canManageGoalsCatalog()) {
            return true;
        }

        return in_array($roleValue, ['admin', 'manager'], true);
    }

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('meta_resultado_construcciones')
            && Schema::hasTable('meta_resultado_construccion_comentarios');
    }

    private function ensureConstructionTasks(): void
    {
        MetaResultado::query()
            ->select('id')
            ->where('activo', true)
            ->whereDoesntHave('construccion')
            ->lazyById()
            ->each(fn (MetaResultado $meta) => MetaResultadoConstruccion::query()->firstOrCreate([
                'meta_resultado_id' => $meta->id,
            ]));
    }

    private function reviewers()
    {
        return User::query()
            ->where('active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->canReviewOdsIndicators())
            ->sortBy('name')
            ->values();
    }

    /**
     * @return array{percentage: float, rows: \Illuminate\Support\Collection<int, array<string, mixed>>, metas_count: int}
     */
    private function managementProgress(?MetaResultado $metaResultado, MetaProductoAvance $avance): array
    {
        if (! $metaResultado) {
            return ['percentage' => 0.0, 'rows' => collect(), 'metas_count' => 0];
        }

        $metas = $metaResultado->relationLoaded('metasProducto')
            ? $metaResultado->metasProducto
            : $metaResultado->metasProducto()->get();

        $rows = $metas->map(function (MetaProducto $meta) use ($avance): array {
            $actual = $avance->resumenActual($meta);

            return [
                'meta' => $meta,
                'resumen' => $actual,
                'percentage' => max(0.0, min(100.0, (float) ($actual['porcentaje_fisico'] ?? 0))),
            ];
        });

        return [
            'percentage' => $rows->isEmpty() ? 0.0 : round((float) $rows->avg('percentage'), 4),
            'rows' => $rows,
            'metas_count' => $metas->count(),
        ];
    }

    private function resultProgress(MetaResultadoConstruccion $task, float $managementProgress): ?float
    {
        if ($task->measurement_mode === 'management_as_result') {
            return round(max(0.0, min(100.0, $managementProgress)), 4);
        }

        if ($task->baseline_value === null || $task->current_value === null) {
            return null;
        }

        $baseline = (float) $task->baseline_value;
        $current = (float) $task->current_value;
        $target = (float) ($task->metaResultado?->meta_cuatrienio ?? $task->metaResultado?->indicador?->meta_cuatrienio ?? 0);
        $orientation = $task->metaResultado?->indicador?->orientacion;

        if ($orientation === OrientacionIndicador::Reduccion) {
            $denominator = $baseline - $target;
            $progress = $denominator != 0.0 ? (($baseline - $current) / $denominator) * 100 : null;
        } elseif ($orientation === OrientacionIndicador::Mantenimiento) {
            $reference = abs($target) > 0.0 ? abs($target) : max(abs($baseline), 1.0);
            $progress = 100 - ((abs($current - $target) / $reference) * 100);
        } else {
            $denominator = $target - $baseline;
            $progress = $denominator != 0.0 ? (($current - $baseline) / $denominator) * 100 : null;
        }

        return $progress === null ? null : round(max(0.0, min(100.0, $progress)), 4);
    }

    /**
     * @return array<string, string>
     */
    private function availableStatusesFor(?User $user, MetaResultadoConstruccion $task): array
    {
        if (! $user?->mustSeeOnlyAssignedOdsReviews()) {
            return self::STATUS;
        }

        if ($this->isValidationOrClosedStatus($task->status)) {
            return [$task->status => self::STATUS[$task->status] ?? $task->status];
        }

        return collect([
            $task->status,
            'pending',
            'in_review',
            'pending_validation',
            'needs_adjustment',
        ])
            ->unique()
            ->filter(fn (string $status): bool => array_key_exists($status, self::STATUS))
            ->mapWithKeys(fn (string $status): array => [$status => self::STATUS[$status]])
            ->all();
    }

    private function reviewerLocked(Request $request, MetaResultadoConstruccion $task): bool
    {
        return ($request->user()?->mustSeeOnlyAssignedOdsReviews() ?? false)
            && $this->isValidationOrClosedStatus($task->status);
    }

    private function isValidationOrClosedStatus(string $status): bool
    {
        return in_array($status, ['pending_validation', 'completed', 'not_applicable'], true);
    }

    private function comment(MetaResultadoConstruccion $task, ?User $user, string $comment, string $eventType = 'comment', ?array $metadata = null): MetaResultadoConstruccionComentario
    {
        return MetaResultadoConstruccionComentario::query()->create([
            'construccion_id' => $task->id,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'comment' => $comment,
            'metadata' => $metadata,
        ]);
    }

    private function showUrl(MetaResultadoConstruccion $task): string
    {
        return \App\Filament\Pages\Workspace::getUrl([
            'workspace' => 'construccion-meta-resultado',
            'record' => $task->getRouteKey(),
        ]);
    }
}
