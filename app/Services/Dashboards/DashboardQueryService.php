<?php

namespace App\Services\Dashboards;

use App\Models\TabularDataSourceVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        $filters = collect($filters)->filter(fn ($value, $field): bool => $value !== null && $value !== '' && $allowedFilters->contains($field))->all();

        if (($query['operation'] ?? null) === 'population_indicator') {
            return $this->populationIndicator($version, $fields, $filters, $query, $public);
        }
        if (($query['operation'] ?? null) === 'population_area_distribution') {
            return $this->populationAreaDistribution($version, $fields, $filters, $query, $public);
        }
        if (($query['operation'] ?? null) === 'population_pyramid') {
            return $this->populationPyramid($version, $fields, $filters, $query, $public);
        }

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

    /** @param Collection<string, array<string, mixed>> $fields
     * @param  array<string, scalar|null>  $filters
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function populationIndicator(TabularDataSourceVersion $version, Collection $fields, array $filters, array $query, bool $public): array
    {
        $field = (string) ($query['field'] ?? '');
        $this->ensurePopulationField($fields, $field, $public);
        $filters = $this->populationFilters($fields, $filters, $query, $public, true);

        if (DB::getDriverName() === 'pgsql') {
            [$where, $whereBindings] = $this->postgresFilters($version, $filters);
            $value = DB::selectOne(<<<SQL
                SELECT COALESCE(SUM(
                    CASE WHEN jsonb_extract_path_text(item, ?) ~ '^-?[0-9]+([.][0-9]+)?$'
                         THEN jsonb_extract_path_text(item, ?)::numeric ELSE 0 END
                ), 0)::double precision AS value
                FROM tabular_data_source_versions AS versions
                CROSS JOIN LATERAL jsonb_array_elements(versions.records) AS item
                WHERE {$where}
                SQL, [$field, $field, ...$whereBindings])->value ?? 0;

            return ['type' => 'value', 'value' => (float) $value];
        }

        $rows = $this->filteredMemoryRows($version, $filters);

        return ['type' => 'value', 'value' => $this->aggregate($rows, 'sum', $field), 'count' => $rows->count()];
    }

    /** @param Collection<string, array<string, mixed>> $fields
     * @param  array<string, scalar|null>  $filters
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function populationAreaDistribution(TabularDataSourceVersion $version, Collection $fields, array $filters, array $query, bool $public): array
    {
        $field = (string) ($query['field'] ?? 'total');
        $this->ensurePopulationField($fields, $field, $public);
        $filters = $this->populationFilters($fields, $filters, $query, $public, false);
        $areas = ['Cabecera Municipal', 'Centros Poblados y Rural Disperso'];

        if (DB::getDriverName() === 'pgsql') {
            [$where, $whereBindings] = $this->postgresFilters($version, $filters);
            $bindings = [$field, $field, ...$whereBindings, ...$areas];
            $rows = collect(DB::select(<<<SQL
                SELECT jsonb_extract_path_text(item, 'area_geografica') AS label,
                       COALESCE(SUM(CASE WHEN jsonb_extract_path_text(item, ?) ~ '^-?[0-9]+([.][0-9]+)?$'
                                         THEN jsonb_extract_path_text(item, ?)::numeric ELSE 0 END), 0)::double precision AS value
                FROM tabular_data_source_versions AS versions
                CROSS JOIN LATERAL jsonb_array_elements(versions.records) AS item
                WHERE {$where}
                  AND jsonb_extract_path_text(item, 'area_geografica') IN (?, ?)
                GROUP BY jsonb_extract_path_text(item, 'area_geografica')
                SQL, $bindings))->map(fn (object $row): array => ['label' => (string) $row->label, 'value' => (float) $row->value]);

            return ['type' => 'series', 'rows' => $rows->values()->all()];
        }

        $rows = $this->filteredMemoryRows($version, $filters)->filter(fn (array $row): bool => in_array($row['area_geografica'] ?? null, $areas, true));

        return ['type' => 'series', 'rows' => $rows->groupBy('area_geografica')->map(fn (Collection $items, string $label): array => ['label' => $label, 'value' => $this->aggregate($items, 'sum', $field)])->values()->all()];
    }

    /** @param Collection<string, array<string, mixed>> $fields */
    private function ensurePopulationField(Collection $fields, string $field, bool $public): void
    {
        if (! $fields->has($field) || ($public && data_get($fields->get($field), 'visibility') === 'internal')) {
            throw ValidationException::withMessages(['query' => "El campo {$field} no está disponible para el componente poblacional."]);
        }
    }

    /** @param Collection<string, array<string, mixed>> $fields
     * @param  array<string, scalar|null>  $filters
     * @param  array<string, mixed>  $query
     * @return array<string, scalar|null>
     */
    private function populationFilters(Collection $fields, array $filters, array $query, bool $public, bool $includeArea): array
    {
        $fixed = ['ano' => $query['year'] ?? '2026'];
        if ($includeArea) {
            $fixed['area_geografica'] = $query['area'] ?? 'Total';
        }
        foreach ($fixed as $field => $value) {
            if (! $fields->has($field) || ($public && data_get($fields->get($field), 'visibility') === 'internal')) {
                throw ValidationException::withMessages(['query' => "El campo {$field} no está disponible para filtrar el componente poblacional."]);
            }
            $filters[$field] = $value;
        }

        return $filters;
    }

    /** @param array<string, scalar|null> $filters
     * @return array{0: string, 1: array<int, scalar|null>}
     */
    private function postgresFilters(TabularDataSourceVersion $version, array $filters): array
    {
        $clauses = ['versions.id = ?'];
        $bindings = [$version->id];
        foreach ($filters as $field => $value) {
            $clauses[] = 'jsonb_extract_path_text(item, ?) = ?';
            $bindings[] = $field;
            $bindings[] = (string) $value;
        }

        return [implode(' AND ', $clauses), $bindings];
    }

    /** @param array<string, scalar|null> $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filteredMemoryRows(TabularDataSourceVersion $version, array $filters): Collection
    {
        return collect($version->records)->filter(function (array $row) use ($filters): bool {
            foreach ($filters as $field => $value) {
                if ((string) ($row[$field] ?? '') !== (string) $value) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $fields
     * @param  array<string, scalar|null>  $filters
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function populationPyramid(TabularDataSourceVersion $version, Collection $fields, array $filters, array $query, bool $public): array
    {
        $ageFields = $fields->filter(function (array $field, string $key) use ($public): bool {
            return preg_match('/^(hombres|mujeres)_\d+_ano(?:s)?(?:_y_mas)?$/', $key) === 1
                && (! $public || ($field['visibility'] ?? null) !== 'internal');
        })->keys();

        if ($ageFields->isEmpty()) {
            throw ValidationException::withMessages(['query' => 'La fuente no contiene columnas de población por sexo y edad.']);
        }

        $fixedFilters = array_filter([
            'ano' => $query['year'] ?? null,
            'area_geografica' => $query['area'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== '');
        foreach ($fixedFilters as $field => $value) {
            if (! $fields->has($field) || ($public && data_get($fields->get($field), 'visibility') === 'internal')) {
                throw ValidationException::withMessages(['query' => "El campo {$field} no está disponible para filtrar la pirámide."]);
            }
            $filters[$field] = $value;
        }

        $totals = DB::getDriverName() === 'pgsql'
            ? $this->postgresPopulationTotals($version, $ageFields, $filters)
            : $this->memoryPopulationTotals($version, $ageFields, $filters);

        $groups = [
            ['label' => '60-100+', 'from' => 60, 'to' => PHP_INT_MAX],
            ['label' => '27-59', 'from' => 27, 'to' => 59],
            ['label' => '19-26', 'from' => 19, 'to' => 26],
            ['label' => '12-18', 'from' => 12, 'to' => 18],
            ['label' => '6-11', 'from' => 6, 'to' => 11],
            ['label' => '0-5', 'from' => 0, 'to' => 5],
        ];

        return ['type' => 'series', 'rows' => collect($groups)->map(function (array $group) use ($totals): array {
            $female = 0;
            $male = 0;
            foreach ($totals as $field => $value) {
                if (preg_match('/^(hombres|mujeres)_(\d+)_/', $field, $matches) !== 1) {
                    continue;
                }
                $age = (int) $matches[2];
                if ($age < $group['from'] || $age > $group['to']) {
                    continue;
                }
                if ($matches[1] === 'mujeres') {
                    $female += $value;
                } else {
                    $male += $value;
                }
            }

            return ['label' => $group['label'], 'value' => $female + $male, 'series' => [
                ['label' => 'Mujeres', 'value' => $female],
                ['label' => 'Hombres', 'value' => $male],
            ]];
        })->all()];
    }

    /**
     * @param  Collection<int, string>  $ageFields
     * @param  array<string, scalar|null>  $filters
     * @return Collection<string, float>
     */
    private function postgresPopulationTotals(TabularDataSourceVersion $version, Collection $ageFields, array $filters): Collection
    {
        $clauses = ['versions.id = ?'];
        $bindings = [$version->id];
        foreach ($filters as $field => $value) {
            $clauses[] = 'jsonb_extract_path_text(item, ?) = ?';
            $bindings[] = $field;
            $bindings[] = (string) $value;
        }
        $placeholders = $ageFields->map(fn (): string => '?')->implode(', ');
        $bindings = [...$bindings, ...$ageFields->all()];
        $where = implode(' AND ', $clauses);

        return collect(DB::select(<<<SQL
            SELECT attribute.key, SUM(
                CASE WHEN attribute.value ~ '^-?[0-9]+([.][0-9]+)?$' THEN attribute.value::numeric ELSE 0 END
            )::double precision AS value
            FROM tabular_data_source_versions AS versions
            CROSS JOIN LATERAL jsonb_array_elements(versions.records) AS item
            CROSS JOIN LATERAL jsonb_each_text(item) AS attribute
            WHERE {$where}
              AND attribute.key IN ({$placeholders})
            GROUP BY attribute.key
            SQL, $bindings))->mapWithKeys(fn (object $row): array => [(string) $row->key => (float) $row->value]);
    }

    /**
     * @param  Collection<int, string>  $ageFields
     * @param  array<string, scalar|null>  $filters
     * @return Collection<string, float>
     */
    private function memoryPopulationTotals(TabularDataSourceVersion $version, Collection $ageFields, array $filters): Collection
    {
        $totals = collect($ageFields->mapWithKeys(fn (string $field): array => [$field => 0.0]));
        foreach ($version->records as $row) {
            foreach ($filters as $field => $value) {
                if ((string) ($row[$field] ?? '') !== (string) $value) {
                    continue 2;
                }
            }
            foreach ($ageFields as $field) {
                if (is_numeric($row[$field] ?? null)) {
                    $totals[$field] += (float) $row[$field];
                }
            }
        }

        return $totals;
    }
}
