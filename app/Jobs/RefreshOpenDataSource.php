<?php

namespace App\Jobs;

use App\Models\OpenDataSource;
use App\Services\OpenData\AnalyzeDatosGovDataset;
use App\Services\OpenData\BuildOpenDataGeoJson;
use App\Services\Investments\SocrataClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RefreshOpenDataSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public readonly string $sourceId) {}

    public function handle(BuildOpenDataGeoJson $builder, AnalyzeDatosGovDataset $analyzer, SocrataClient $socrata): void
    {
        $source = OpenDataSource::find($this->sourceId);
        if (! $source) {
            return;
        }

        $source->update(['status' => 'refreshing', 'last_checked_at' => now()]);
        try {
            $analysis = $analyzer->handle($source->landing_page_url);
            if (! hash_equals($source->schema_signature, $analysis['schema_signature'])) {
                $source->update(['status' => 'needs_review', 'last_error' => 'La estructura del conjunto cambió. SIID conserva la última versión válida.']);

                return;
            }

            $suggestions = collect($analysis['suggested_filters'])->keyBy('field');
            $filters = collect($source->filters ?? [])->map(function (array $filter) use ($suggestions, $socrata, $source): array {
                $fresh = $suggestions->get($filter['field']);
                try {
                    $rows = $socrata->query($source->dataset_id, [
                        '$select' => $filter['field'],
                        '$where' => $filter['field'].' is not null',
                        '$group' => $filter['field'],
                        '$order' => $filter['field'],
                        '$limit' => 101,
                    ]);
                    $options = collect($rows)->pluck($filter['field'])->filter(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '')
                        ->map(fn ($value): string => (string) $value)->unique()->take(100)->values()->all();
                } catch (Throwable) {
                    $options = $fresh['options'] ?? $filter['options'] ?? [];
                }

                return [...$filter, 'options' => $options];
            })->values()->all();
            $source->update(['metadata' => $analysis['metadata'], 'filters' => $filters]);
            $source->layer->update(['filters' => collect($filters)->map(fn (array $filter): array => [
                'name' => $filter['field'],
                'label' => $filter['label'],
                'default' => (string) ($filter['options'][0] ?? ''),
                'options' => collect($filter['options'] ?? [])->map(fn ($value): array => ['value' => (string) $value, 'label' => (string) $value])->all(),
            ])->all()]);

            $filterSets = $source->snapshots()->get()->pluck('filters')->push([])->unique(fn ($filters): string => json_encode($filters))->values();
            foreach ($filterSets as $filters) {
                $builder->handle($source, is_array($filters) ? $filters : [], true);
            }
        } catch (Throwable $exception) {
            report($exception);
            $source->update(['status' => 'error', 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);
            throw $exception;
        }
    }
}
