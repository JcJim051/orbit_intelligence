<?php

namespace App\Services\Ods;

use App\Enums\UserRole;
use App\Models\IndicadorResultado;
use App\Models\IndicadorResultadoOdsComment;
use App\Models\IndicadorResultadoOdsReview;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignOdsReviewTeam
{
    /** @var list<string> */
    public const REVIEWER_NAMES = ['luisa', 'diana', 'braian', 'fabian', 'clara'];

    public const VALIDATOR_NAME = 'bibiana';

    /** @var array<int, string> */
    private array $matchedReviewerNames = [];

    /**
     * Crea las tareas faltantes, configura roles del equipo ODS y asigna únicamente
     * indicadores sin responsable. Las asignaciones existentes se conservan.
     *
     * @return array{reviewers:int, validator:bool, assigned:int, missing_reviewers:list<string>, missing_validator:bool, distribution:array<string, int>}
     */
    public function assignPending(?User $actor = null): array
    {
        return DB::transaction(function () use ($actor): array {
            $this->ensureReviewTasks();

            $reviewers = $this->reviewers();
            $validator = $this->findUserByName(self::VALIDATOR_NAME);

            $reviewers->each(fn (User $user) => $this->ensureRole($user, UserRole::OdsReviewer));

            if ($validator) {
                $this->ensureRole($validator, UserRole::OdsValidator);
            }

            $distribution = $reviewers
                ->mapWithKeys(fn (User $user): array => [
                    $user->id => IndicadorResultadoOdsReview::query()
                        ->where('assigned_to', $user->id)
                        ->count(),
                ])
                ->all();

            $assigned = 0;

            if ($reviewers->isNotEmpty()) {
                IndicadorResultadoOdsReview::query()
                    ->with('indicador:id,nombre')
                    ->whereNull('assigned_to')
                    ->orderBy('id')
                    ->get()
                    ->each(function (IndicadorResultadoOdsReview $task) use ($reviewers, &$distribution, &$assigned, $actor, $validator): void {
                        $reviewer = $this->leastLoadedReviewer($reviewers, $distribution);

                        if (! $reviewer) {
                            return;
                        }

                        $task->update([
                            'assigned_to' => $reviewer->id,
                            'status' => $task->status === 'pending' ? 'in_review' : $task->status,
                        ]);

                        $distribution[$reviewer->id] = ($distribution[$reviewer->id] ?? 0) + 1;
                        $assigned++;

                        IndicadorResultadoOdsComment::query()->create([
                            'review_id' => $task->id,
                            'user_id' => $actor?->id,
                            'event_type' => 'auto_assignment',
                            'comment' => "Asignación automática de revisión ODS a {$reviewer->name}.",
                            'metadata' => [
                                'assigned_to' => $reviewer->id,
                                'assigned_to_name' => $reviewer->name,
                                'indicator' => $task->indicador?->nombre,
                                'strategy' => 'balanced_pending_only',
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
