<?php

namespace App\Http\Controllers;

use App\Enums\DataFieldVisibility;
use App\Enums\IndicatorStatus;
use App\Models\Indicator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IndicatorDataSeriesController extends Controller
{
    public function __invoke(Indicator $indicator): StreamedResponse
    {
        abort_unless($indicator->status === IndicatorStatus::Published, 404);

        $version = $indicator->tabularDataSource?->currentVersion;
        abort_unless($version, 404);

        $fields = collect($version->fields ?? [])
            ->filter(fn (array $field): bool => ($field['visibility'] ?? DataFieldVisibility::Analytics->value) !== DataFieldVisibility::Internal->value)
            ->values();
        abort_if($fields->isEmpty(), 404, 'La serie no tiene campos públicos o disponibles para análisis.');

        $keys = $fields->pluck('key')->all();
        $labels = $fields->map(fn (array $field): string => (string) ($field['label'] ?? $field['key']))->all();

        return response()->streamDownload(function () use ($version, $keys, $labels): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $labels);
            foreach ($version->records ?? [] as $row) {
                fputcsv($handle, collect($keys)->map(fn (string $key) => $row[$key] ?? null)->all());
            }
            fclose($handle);
        }, $indicator->slug.'-serie-datos.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
