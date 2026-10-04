<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\IndicadorResultado;
use App\Models\IndicadorResultadoOdsComment;
use App\Models\IndicadorResultadoOdsLink;
use App\Models\IndicadorResultadoOdsReview;
use App\Models\OdsGoal;
use App\Models\OdsIndicator;
use App\Models\OdsTarget;
use App\Models\User;
use App\Services\Ods\AssignOdsReviewTeam;
use App\Services\Ods\SuggestOdsIndicatorRelations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OdsIndicatorReviewController extends Controller
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
    public const RELATION_TYPES = [
        'direct' => 'Directa',
        'partial' => 'Parcial',
        'contextual' => 'Contextual',
        'not_applicable' => 'No aplica',
    ];

    /** @var array<string, string> */
    public const CONFIDENCE = [
        'high' => 'Alta',
        'medium' => 'Media',
        'low' => 'Baja',
    ];

    public function index(Request $request): View
    {
        $this->authorizeAccess($request);
        $this->ensureReviewTasks();

        $query = IndicadorResultadoOdsReview::query()
            ->with([
                'indicador.metasResultado.programa.linea.eje.pilar',
                'indicador.metasResultado.subprograma.programa.linea.eje.pilar',
                'assignedUser',
                'links.odsIndicator.target.goal',
            ])
            ->withCount('links')
            ->latest('updated_at');

        if ($request->user()->mustSeeOnlyAssignedOdsReviews()) {
            $query->where('assigned_to', $request->user()->id);
        }

        if ($request->filled('status') && array_key_exists((string) $request->query('status'), self::STATUS)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->user()->mustSeeOnlyAssignedOdsReviews()) {
            // Los roles dedicados no pueden consultar tareas de otros revisores.
        } elseif ($request->query('assigned') === 'me') {
            $query->where('assigned_to', $request->user()->id);
        } elseif (ctype_digit((string) $request->query('assigned'))) {
            $query->where('assigned_to', (int) $request->query('assigned'));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = "%{$search}%";
            $normalizedStatus = collect(self::STATUS)
                ->filter(fn (string $label, string $status): bool => str_contains(mb_strtolower($label), mb_strtolower($search)) || str_contains($status, mb_strtolower($search)))
                ->keys()
                ->all();
            $normalizedRelationStatus = collect([
                'proposed' => 'Propuesta',
                'accepted' => 'Confirmada',
                'rejected' => 'Rechazada',
            ])->filter(fn (string $label, string $status): bool => str_contains(mb_strtolower($label), mb_strtolower($search)) || str_contains($status, mb_strtolower($search)))
                ->keys()
                ->all();

            $query->where(function ($query) use ($like, $normalizedStatus, $normalizedRelationStatus): void {
                $query
                    ->whereIn('status', $normalizedStatus)
                    ->orWhereHas('indicador', fn ($query) => $query
                        ->where('nombre', 'like', $like)
                        ->orWhere('codigo', 'like', $like)
                        ->orWhere('unidad_medida', 'like', $like)
                        ->orWhereHas('metasResultado', fn ($query) => $query
                            ->where('descripcion', 'like', $like)
                            ->orWhere('codigo_provisional', 'like', $like)
                            ->orWhere('codigo', 'like', $like)))
                    ->orWhereHas('assignedUser', fn ($query) => $query
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like))
                    ->orWhereHas('links', fn ($query) => $query
                        ->whereIn('status', $normalizedRelationStatus)
                        ->orWhere('relation_type', 'like', $like)
                        ->orWhere('confidence', 'like', $like)
                        ->orWhere('justification', 'like', $like)
                        ->orWhereHas('odsIndicator', fn ($query) => $query
                            ->where('code', 'like', $like)
                            ->orWhere('name', 'like', $like)
                            ->orWhere('description', 'like', $like)
                            ->orWhereHas('target', fn ($query) => $query
                                ->where('code', 'like', $like)
                                ->orWhere('name', 'like', $like)
                                ->orWhereHas('goal', fn ($query) => $query
                                    ->where('code', 'like', $like)
                                    ->orWhere('name', 'like', $like)))))
                    ->orWhereHas('comments', fn ($query) => $query
                        ->where('comment', 'like', $like)
                        ->orWhere('event_type', 'like', $like)
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)));
            });
        }

        $reviews = $query->get();

        return view('intelligence.ods-reviews.index', [
            'reviews' => $reviews,
            'filters' => [
                'q' => $search,
                'status' => (string) $request->query('status', ''),
                'assigned' => (string) $request->query('assigned', ''),
            ],
            'statuses' => self::STATUS,
            'reviewers' => $this->reviewers(),
            'summary' => IndicadorResultadoOdsReview::query()
                ->when($request->user()->mustSeeOnlyAssignedOdsReviews(), fn ($query) => $query->where('assigned_to', $request->user()->id))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'canManageOdsAssignments' => ! $request->user()->isDedicatedOdsReviewer(),
            'canGenerateOdsSuggestions' => ! $request->user()->isDedicatedOdsReviewer(),
        ]);
    }

    public function suggest(Request $request, SuggestOdsIndicatorRelations $suggestions): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_if($request->user()->isDedicatedOdsReviewer(), 403);
        $this->ensureReviewTasks();

        $result = $suggestions->generate($request->user());

        return redirect()
            ->to(\App\Filament\Pages\Workspace::getUrl(['workspace' => 'revision-ods']))
            ->with('status', "Sugerencias generadas: {$result['created']} relación(es) propuesta(s), {$result['reviewed']} indicador(es) revisado(s), {$result['skipped_existing']} omitida(s) por existir relación previa.");
    }

    public function assignTeam(Request $request, AssignOdsReviewTeam $assignments): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_if($request->user()->isDedicatedOdsReviewer(), 403);

        $result = $assignments->assignPending($request->user());
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

        $message = "Reparto ODS actualizado: {$result['assigned']} indicador(es) pendiente(s) asignado(s). Distribución actual: {$distribution}.";

        if ($warnings !== []) {
            $message .= ' '.implode(' ', $warnings);
        }

        return redirect()
            ->to(\App\Filament\Pages\Workspace::getUrl(['workspace' => 'revision-ods']))
            ->with('status', $message);
    }

    public function show(Request $request, IndicadorResultadoOdsReview $task): View
    {
        $this->authorizeAccess($request);
        abort_if($request->user()->mustSeeOnlyAssignedOdsReviews() && $task->assigned_to !== $request->user()->id, 403);

        $task->load([
            'indicador.metasResultado.programa.linea.eje.pilar',
            'indicador.metasResultado.subprograma.programa.linea.eje.pilar',
            'assignedUser',
            'links.odsIndicator.target.goal',
            'links.comments.user',
            'links.creator',
            'links.reviewer',
            'comments.user',
            'comments.link.odsIndicator',
        ]);

        $reviewerLocked = $this->reviewerLocked($request, $task);

        return view('intelligence.ods-reviews.show', [
            'task' => $task,
            'statuses' => self::STATUS,
            'availableStatuses' => $this->availableStatusesFor($request->user(), $task),
            'relationTypes' => self::RELATION_TYPES,
            'confidenceLevels' => self::CONFIDENCE,
            'reviewers' => $this->reviewers(),
            'canConfirmOdsRelations' => $request->user()?->canConfirmOdsIndicatorRelations() ?? false,
            'canEditOdsReview' => ! $reviewerLocked,
            'canEditOdsRelations' => ! $reviewerLocked,
            'reviewerLocked' => $reviewerLocked,
            'odsIndicators' => OdsIndicator::query()
                ->with(['target.goal'])
                ->where('active', true)
                ->orderBy('code')
                ->get(),
            'canManageOdsAssignments' => ! $request->user()->isDedicatedOdsReviewer(),
        ]);
    }

    public function update(Request $request, IndicadorResultadoOdsReview $task): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_if($request->user()->mustSeeOnlyAssignedOdsReviews() && $task->assigned_to !== $request->user()->id, 403);
        abort_if($this->reviewerLocked($request, $task), 403);

        $availableStatuses = $this->availableStatusesFor($request->user(), $task);

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(array_keys($availableStatuses))],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);

        DB::transaction(function () use ($request, $task, $validated): void {
            $old = $task->only(['assigned_to', 'status']);
            $statusChanged = $task->status !== $validated['status'];

            $task->update([
                'assigned_to' => $request->user()->isDedicatedOdsReviewer() ? $task->assigned_to : ($validated['assigned_to'] ?? null),
                'status' => $validated['status'],
                'reviewed_by' => in_array($validated['status'], ['completed', 'not_applicable'], true) ? $request->user()->id : $task->reviewed_by,
                'reviewed_at' => in_array($validated['status'], ['completed', 'not_applicable'], true) ? now() : $task->reviewed_at,
            ]);

            $this->comment($task, $request->user(), $validated['comment'] ?: 'Actualización de estado/asignación.', 'workflow', [
                'before' => $old,
                'after' => $task->only(['assigned_to', 'status']),
                'status_changed' => $statusChanged,
            ]);
        });

        return redirect($this->showUrl($task))->with('status', 'Revisión ODS actualizada.');
    }

    public function storeLink(Request $request, IndicadorResultadoOdsReview $task): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_if($request->user()->mustSeeOnlyAssignedOdsReviews() && $task->assigned_to !== $request->user()->id, 403);
        abort_if($this->reviewerLocked($request, $task), 403);

        $validated = $request->validate([
            'ods_indicator_id' => ['nullable', 'integer', 'exists:ods_indicators,id'],
            'ods_goal_code' => ['required_without:ods_indicator_id', 'nullable', 'string', 'max:8'],
            'ods_goal_name' => ['required_without:ods_indicator_id', 'nullable', 'string', 'max:255'],
            'ods_target_code' => ['required_without:ods_indicator_id', 'nullable', 'string', 'max:16'],
            'ods_target_name' => ['required_without:ods_indicator_id', 'nullable', 'string', 'max:1000'],
            'ods_indicator_code' => ['required_without:ods_indicator_id', 'nullable', 'string', 'max:32'],
            'ods_indicator_name' => ['required_without:ods_indicator_id', 'nullable', 'string', 'max:1000'],
            'ods_indicator_unit' => ['nullable', 'string', 'max:255'],
            'relation_type' => ['required', Rule::in(array_keys(self::RELATION_TYPES))],
            'confidence' => ['required', Rule::in(array_keys(self::CONFIDENCE))],
            'status' => ['required', Rule::in(['proposed', 'accepted', 'rejected'])],
            'justification' => ['required', 'string', 'min:10', 'max:4000'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);

        if ($validated['status'] === 'accepted') {
            abort_unless($request->user()?->canConfirmOdsIndicatorRelations(), 403);
        }

        DB::transaction(function () use ($request, $task, $validated): void {
            if (! empty($validated['ods_indicator_id'])) {
                $indicator = OdsIndicator::query()->findOrFail($validated['ods_indicator_id']);
            } else {
                $goal = OdsGoal::query()->updateOrCreate(
                    ['code' => trim((string) $validated['ods_goal_code'])],
                    ['name' => trim((string) $validated['ods_goal_name']), 'active' => true],
                );
                $target = OdsTarget::query()->updateOrCreate(
                    ['code' => trim((string) $validated['ods_target_code'])],
                    ['ods_goal_id' => $goal->id, 'name' => trim((string) $validated['ods_target_name']), 'active' => true],
                );
                $indicator = OdsIndicator::query()->updateOrCreate(
                    ['code' => trim((string) $validated['ods_indicator_code'])],
                    [
                        'ods_target_id' => $target->id,
                        'name' => trim((string) $validated['ods_indicator_name']),
                        'unit' => $validated['ods_indicator_unit'] ? trim($validated['ods_indicator_unit']) : null,
                        'active' => true,
                    ],
                );
            }

            $link = IndicadorResultadoOdsLink::query()->updateOrCreate(
                [
                    'indicador_resultado_id' => $task->indicador_resultado_id,
                    'ods_indicator_id' => $indicator->id,
                ],
                [
                    'review_id' => $task->id,
                    'relation_type' => $validated['relation_type'],
                    'confidence' => $validated['confidence'],
                    'status' => $validated['status'],
                    'justification' => trim($validated['justification']),
                    'created_by' => $request->user()->id,
                    'reviewed_by' => $validated['status'] !== 'proposed' ? $request->user()->id : null,
                    'reviewed_at' => $validated['status'] !== 'proposed' ? now() : null,
                ],
            );

            if ($task->status === 'pending') {
                $task->update(['status' => 'in_review', 'assigned_to' => $task->assigned_to ?? $request->user()->id]);
            }

            $this->comment($task, $request->user(), $validated['comment'] ?: 'Relación ODS registrada o actualizada.', 'link_saved', [
                'link_id' => $link->id,
                'ods_indicator_code' => $indicator->code,
                'relation_type' => $validated['relation_type'],
                'confidence' => $validated['confidence'],
                'status' => $validated['status'],
            ], $link);
        });

        return redirect($this->showUrl($task))->with('status', 'Relación ODS guardada con trazabilidad.');
    }

    public function updateLink(Request $request, IndicadorResultadoOdsReview $task, IndicadorResultadoOdsLink $link): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($link->review_id === $task->id, 404);
        abort_if($request->user()->mustSeeOnlyAssignedOdsReviews() && $task->assigned_to !== $request->user()->id, 403);
        abort_if($this->reviewerLocked($request, $task), 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
            'comment' => ['required', 'string', 'min:5', 'max:4000'],
        ]);

        if ($validated['decision'] === 'accept') {
            abort_unless($request->user()?->canConfirmOdsIndicatorRelations(), 403);
        }

        DB::transaction(function () use ($request, $task, $link, $validated): void {
            $status = $validated['decision'] === 'accept' ? 'accepted' : 'rejected';

            $link->update([
                'status' => $status,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $this->comment(
                $task,
                $request->user(),
                $validated['comment'],
                $status === 'accepted' ? 'link_accepted' : 'link_rejected',
                [
                    'link_id' => $link->id,
                    'ods_indicator_code' => $link->odsIndicator?->code,
                    'decision' => $validated['decision'],
                ],
                $link,
            );

            if ($status === 'accepted') {
                $task->update([
                    'status' => 'completed',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);
            } elseif ($task->status === 'pending') {
                $task->update(['status' => 'in_review']);
            }
        });

        return redirect($this->showUrl($task))->with('status', $validated['decision'] === 'accept' ? 'Relación ODS confirmada.' : 'Relación ODS rechazada.');
    }

    public function storeComment(Request $request, IndicadorResultadoOdsReview $task): RedirectResponse
    {
        $this->authorizeAccess($request);
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

    private function ensureReviewTasks(): void
    {
        IndicadorResultado::query()
            ->select('id')
            ->whereDoesntHave('odsReview')
            ->lazyById()
            ->each(fn (IndicadorResultado $indicador) => IndicadorResultadoOdsReview::query()->firstOrCreate([
                'indicador_resultado_id' => $indicador->id,
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
     * @return array<string, string>
     */
    private function availableStatusesFor(?User $user, IndicadorResultadoOdsReview $task): array
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

    private function reviewerLocked(Request $request, IndicadorResultadoOdsReview $task): bool
    {
        return ($request->user()?->mustSeeOnlyAssignedOdsReviews() ?? false)
            && $this->isValidationOrClosedStatus($task->status);
    }

    private function isValidationOrClosedStatus(string $status): bool
    {
        return in_array($status, ['pending_validation', 'completed', 'not_applicable'], true);
    }

    private function comment(IndicadorResultadoOdsReview $task, ?User $user, string $comment, string $eventType = 'comment', ?array $metadata = null, ?IndicadorResultadoOdsLink $link = null): IndicadorResultadoOdsComment
    {
        return IndicadorResultadoOdsComment::query()->create([
            'review_id' => $task->id,
            'link_id' => $link?->id,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'comment' => $comment,
            'metadata' => $metadata,
        ]);
    }

    private function showUrl(IndicadorResultadoOdsReview $task): string
    {
        return \App\Filament\Pages\Workspace::getUrl([
            'workspace' => 'revision-ods-detalle',
            'record' => $task->getKey(),
        ]);
    }
}
