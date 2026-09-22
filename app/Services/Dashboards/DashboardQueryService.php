<?php

namespace App\Services\Dashboards;

use App\Models\TabularDataSourceVersion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DashboardQueryService
{
    /** @param array<string, mixed> $query
     * @param  array<string, scalar|null>  $filters
     * @return array<string, mixed>
     */
    public function run(TabularDataSourceVersion $version, array $query, array $filters, bool $public = true): array
    {
        $fields = collect($version->fields)->keyBy('key');
        $requested = array_filter([$query['field'] ?? null, $query['category'] ?? null, $query['series'] ?? null]);
        foreach ($requested as $field) {
            if (! $fields->has($field) || ($public && data_get($fields->get($field), 'visibility') === 'internal')) {
                throw ValidationException::withMessages(['query' => 'La consulta contiene un campo no autorizado.']);
            }
        }
        $allowedFilters = $fields->filter(fn (array $field): bool => ! $public || $field['visibility'] !== 'internal')->keys();
        $rows = collect($version->records)->filter(function (array $row) use ($filters, $allowedFilters): bool {
            foreach ($filters as $field => $value) {
                if ($value !== null && $value !== '' && $allowedFilters->contains($field) && (string) ($row[$field] ?? '') !== (string) $value) {
                    return false;
                }
            }

            return true;
        });
        $operation = in_array($query['operation'] ?? 'count', ['count', 'sum', 'average', 'min', 'max'], true) ? $query['operation'] : 'count';
        if (($query['operation'] ?? null) === 'rows') {
            $visible = $fields->filter(fn (array $field): bool => ! $public || $field['visibility'] === 'public')->keys();

            return ['type' => 'table', 'columns' => $visible->values()->all(), 'rows' => $rows->take(250)->map(fn (array $row): array => collect($row)->only($visible)->all())->values()->all(), 'count' => $rows->count()];
        }
        $category = $query['category'] ?? null;
        $series = $query['series'] ?? null;
        if ($category) {
            $groups = $rows->groupBy(fn (array $row): string => (string) ($row[$category] ?? 'Sin información'));

            return ['type' => 'series', 'rows' => $groups->map(function (Collection $group, string $label) use ($operation, $query, $series): array {
                if ($series) {
                    return ['label' => $label, 'series' => $group->groupBy($series)->map(fn (Collection $items, string $name): array => ['label' => $name, 'value' => $this->aggregate($items, $operation, $query['field'] ?? null)])->values()->all()];
                }

                return ['label' => $label, 'value' => $this->aggregate($group, $operation, $query['field'] ?? null)];
            })->values()->all()];
        }

        return ['type' => 'value', 'value' => $this->aggregate($rows, $operation, $query['field'] ?? null), 'count' => $rows->count()];
    }

    private function aggregate(Collection $rows, string $operation, ?string $field): float|int
    {
        if ($operation === 'count' || $field === null) {
            return $rows->count();
        }
        $values = $rows->pluck($field)->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value);

        return match ($operation) {
            'sum' => $values->sum(),
            'average' => $values->avg() ?? 0,
            'min' => $values->min() ?? 0,
            'max' => $values->max() ?? 0,
            default => 0,
        };
    }
}
