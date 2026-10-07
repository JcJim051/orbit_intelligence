<?php

namespace App\Services\Ods;

use App\Enums\UserRole;
use App\Models\IndicadorResultadoOdsReview;
use App\Models\MetaResultado;
use App\Models\MetaResultadoConstruccion;
use App\Models\MetaResultadoConstruccionComentario;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignMetaResultadoConstructionTeam
{
    /** @var list<string> */
    public const REVIEWER_NAMES = AssignOdsReviewTeam::REVIEWER_NAMES;

    public const VALIDATOR_NAME = AssignOdsReviewTeam::VALIDATOR_NAME;

    /** @var array<int, string> */
    private array $matchedReviewerNames = [];

    /**
     * @return array{reviewers:int, validator:bool, assigned:int, missing_reviewers:list<string>, missing_validator:bool, distribution:array<string, int>}
     */
    public function assignPending(?User $actor = null): array
    {
        return DB::transaction(function () use ($actor): array {
            $this->ensureConstructionTasks();

            $reviewers = $this->reviewers();
            $validator = $this->findUserByName(self::VALIDATOR_NAME);

            $reviewers->each(fn (User $user) => $this->ensureRole($user, UserRole::OdsReviewer));

            if ($validator) {
                $this->ensureRole($validator, UserRole::OdsValidator);
            }

            $distribution = $reviewers
                ->mapWithKeys(fn (User $user): array => [
                    $user->id => MetaResultadoConstruccion::query()
                        ->where('assigned_to', $user->id)
                        ->count(),
                ])
                ->all();

            $assigned = 0;

            if ($reviewers->isNotEmpty()) {
                $reviewerIds = $reviewers->pluck('id')->all();
                $odsAssignments = IndicadorResultadoOdsReview::query()
                    ->whereNotNull('assigned_to')
                    ->whereIn('assigned_to', $reviewerIds)
                    ->pluck('assigned_to', 'indicador_resultado_id');

                MetaResultadoConstruccion::query()
                    ->with('metaResultado:id,codigo,codigo_provisional,descripcion,indicador_resultado_id')
                    ->whereNull('assigned_to')
                    ->orderBy('id')
                    ->get()
                    ->each(function (MetaResultadoConstruccion $task) use ($reviewers, $odsAssignments, &$distribution, &$assigned, $actor, $validator): void {
                        $odsAssignedTo = $task->metaResultado?->indicador_resultado_id
                            ? $odsAssignments->get($task->metaResultado->indicador_resultado_id)
                            : null;
                        $reviewer = $odsAssignedTo
                            ? $reviewers->firstWhere('id', (int) $odsAssignedTo)
                            : $this->leastLoadedReviewer($reviewers, $distribution);

                        if (! $reviewer) {
                            return;
                        }

                        $strategy = $odsAssignedTo ? 'mirrored_ods_indicator_assignment' : 'balanced_pending_only';

                        $task->update([
                            'assigned_to' => $reviewer->id,
                            'status' => $task->status === 'pending' ? 'in_review' : $task->status,
                        ]);

                        $distribution[$reviewer->id] = ($distribution[$reviewer->id] ?? 0) + 1;
                        $assigned++;

                        MetaResultadoConstruccionComentario::query()->create([
                            'construccion_id' => $task->id,
                            'user_id' => $actor?->id,
                            'event_type' => 'auto_assignment',
                            'comment' => $odsAssignedTo
                                ? "Asignación automática de meta resultado a {$reviewer->name}, replicando la distribución ODS del indicador resultado."
                                : "Asignación automática de construcción de meta resultado a {$reviewer->name}.",
                            'metadata' => [
                                'assigned_to' => $reviewer->id,
                                'assigned_to_name' => $reviewer->name,
                                'meta_resultado' => $task->metaResultado?->codigo_provisional ?: $task->metaResultado?->codigo,
                                'indicator_resultado_id' => $task->metaResultado?->indicador_resultado_id,
                                'strategy' => $strategy,
                                'validator' => $validator?->name,
                            ],
                        ]);
                    });
            }

            return [
                'reviewers' => $reviewers->count(),
                'validator' => (bool) $validator,
                'assigned' => $assigned,
                'missing_reviewers' => array_values(array_diff(
                    self::REVIEWER_NAMES,
                    $reviewers->map(fn (User $user): ?string => $this->matchedReviewerNames[$user->id] ?? null)->filter()->all(),
                )),
                'missing_validator' => ! $validator,
                'distribution' => $reviewers
                    ->mapWithKeys(fn (User $user): array => [$user->name => $distribution[$user->id] ?? 0])
                    ->all(),
            ];
        });
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

    /** @return Collection<int, User> */
    private function reviewers(): Collection
    {
        return collect(self::REVIEWER_NAMES)
            ->map(function (string $name): ?User {
                $user = $this->findUserByName($name);

                if ($user) {
                    $this->matchedReviewerNames[$user->id] = $name;
                }

                return $user;
            })
            ->filter()
            ->unique('id')
            ->values();
    }

    private function findUserByName(string $needle): ?User
    {
        $normalizedNeedle = $this->normalize($needle);

        return User::query()
            ->where('active', true)
            ->get()
            ->first(fn (User $user): bool => str_contains($this->normalize($user->name), $normalizedNeedle));
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }

    private function ensureRole(User $user, UserRole $role): void
    {
        if ($user->role === $role) {
            return;
        }

        $user->forceFill(['role' => $role])->save();
    }

    /**
     * @param Collection<int, User> $reviewers
     * @param array<int, int> $distribution
     */
    private function leastLoadedReviewer(Collection $reviewers, array $distribution): ?User
    {
        return $reviewers
            ->sortBy(fn (User $user): string => sprintf('%010d|%s', $distribution[$user->id] ?? 0, $user->name))
            ->first();
    }
}
