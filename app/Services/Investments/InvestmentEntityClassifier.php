<?php

namespace App\Services\Investments;

use App\Models\InvestmentEntity;
use App\Models\InvestmentEntityAssignment;
use App\Models\InvestmentProject;
use Illuminate\Support\Collection;

class InvestmentEntityClassifier
{
    /** @return Collection<int, InvestmentEntityAssignment> */
    public function classify(InvestmentProject $project): Collection
    {
        $exactFields = collect([
            'executing_entity' => $project->executing_entity,
            'responsible_entity' => $project->responsible_entity,
        ])->filter();
        $textFields = collect([
            'name' => $project->name,
            'objective' => $project->objective,
            'budget_program' => $project->budget_program,
            'raw_data' => json_encode($project->raw_data, JSON_UNESCAPED_UNICODE),
        ])->filter();

        $matches = InvestmentEntity::query()->where('active', true)->orderBy('sort_order')->get()
            ->map(function (InvestmentEntity $entity) use ($exactFields, $textFields): ?array {
                $aliases = collect($entity->aliases)->push($entity->name)->filter()->unique();

                foreach ($exactFields as $field => $value) {
                    foreach ($aliases as $alias) {
                        if ($this->normalize((string) $value) === $this->normalize((string) $alias)) {
                            return ['entity' => $entity, 'method' => 'automatic_exact', 'confidence' => 1.0, 'field' => $field, 'value' => $value];
                        }
                    }
                }

                foreach ($textFields as $field => $value) {
                    foreach ($aliases as $alias) {
                        if ($this->containsAlias((string) $value, (string) $alias)) {
                            return ['entity' => $entity, 'method' => 'automatic_text', 'confidence' => 0.75, 'field' => $field, 'value' => $alias];
                        }
                    }
                }

                return null;
            })->filter()->sortByDesc('confidence')->values();

        $hasConfirmedPrimary = $project->entityAssignments()->where('status', 'confirmed')->where('role', 'primary')->exists();

        return $matches->map(function (array $match, int $index) use ($project, &$hasConfirmedPrimary): InvestmentEntityAssignment {
            $assignment = InvestmentEntityAssignment::firstOrNew([
                'investment_project_id' => $project->id,
                'investment_entity_id' => $match['entity']->id,
            ]);

            if ($assignment->exists && in_array($assignment->status, ['confirmed', 'rejected'], true)) {
                return $assignment;
            }

            $isExact = $match['method'] === 'automatic_exact';
            $role = ! $hasConfirmedPrimary && $index === 0 ? 'primary' : 'collaborator';
            $status = $isExact ? 'confirmed' : 'suggested';
            $assignment->fill([
                'role' => $role,
                'status' => $status,
                'method' => $match['method'],
                'confidence' => $match['confidence'],
                'evidence' => ['field' => $match['field'], 'matched_value' => $match['value']],
                'reviewed_by' => null,
                'reviewed_at' => $isExact ? now() : null,
            ])->save();

            if ($status === 'confirmed' && $role === 'primary') {
                $hasConfirmedPrimary = true;
            }

            return $assignment;
        });
    }

    public function classifyAll(): int
    {
        $classified = 0;
        InvestmentProject::query()->where('is_governor_meta', true)->chunkById(100, function (Collection $projects) use (&$classified): void {
            foreach ($projects as $project) {
                if ($this->classify($project)->isNotEmpty()) {
                    $classified++;
                }
            }
        });

        return $classified;
    }

    private function containsAlias(string $haystack, string $alias): bool
    {
        $normalizedHaystack = $this->normalize($haystack);
        $normalizedAlias = $this->normalize($alias);

        if (mb_strlen($normalizedAlias) <= 4) {
            return preg_match('/(?:^|\s)'.preg_quote($normalizedAlias, '/').'(?:$|\s)/u', $normalizedHaystack) === 1;
        }

        return str_contains($normalizedHaystack, $normalizedAlias);
    }

    private function normalize(string $value): string
    {
        return (string) str($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish();
    }
}
