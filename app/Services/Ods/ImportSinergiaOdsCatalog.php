<?php

namespace App\Services\Ods;

use App\Models\OdsGoal;
use App\Models\OdsIndicator;
use App\Models\OdsTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImportSinergiaOdsCatalog
{
    public const SOURCE_URL = 'https://sinergia.dnp.gov.co/ods/Documents/data/descargas.js';

    /** @var array<string, string> */
    private const GOAL_NAMES = [
        '1' => 'Fin de la pobreza',
        '2' => 'Hambre cero',
        '3' => 'Salud y bienestar',
        '4' => 'Educación de calidad',
        '5' => 'Igualdad de género',
        '6' => 'Agua limpia y saneamiento',
        '7' => 'Energía asequible y no contaminante',
        '8' => 'Trabajo decente y crecimiento económico',
        '9' => 'Industria, innovación e infraestructura',
        '10' => 'Reducción de las desigualdades',
        '11' => 'Ciudades y comunidades sostenibles',
        '12' => 'Producción y consumo responsables',
        '13' => 'Acción por el clima',
        '14' => 'Vida submarina',
        '15' => 'Vida de ecosistemas terrestres',
        '16' => 'Paz, justicia e instituciones sólidas',
        '17' => 'Alianzas para lograr los objetivos',
    ];

    /**
     * @return array{goals:int, targets:int, indicators:int}
     */
    public function import(?string $sourceUrl = null): array
    {
        $indicators = $this->fetchIndicators($sourceUrl ?? self::SOURCE_URL);
        $now = now();

        return DB::transaction(function () use ($indicators, $now): array {
            $touchedGoals = [];
            $touchedTargets = [];
            $touchedIndicators = 0;

            foreach ($indicators as $row) {
                $indicatorCode = trim((string) ($row['Indicador'] ?? ''));
                $goalCode = trim((string) ($row['Objetivo'] ?? ''));

                if ($indicatorCode === '' || $goalCode === '') {
                    continue;
                }

                $targetCode = $this->targetCodeFromIndicator($indicatorCode, $goalCode);
                $goal = OdsGoal::query()->updateOrCreate(
                    ['code' => $goalCode],
                    [
                        'name' => self::GOAL_NAMES[$goalCode] ?? "ODS {$goalCode}",
                        'active' => true,
                    ],
                );

                $target = OdsTarget::query()->updateOrCreate(
                    ['code' => $targetCode],
                    [
                        'ods_goal_id' => $goal->id,
                        'name' => "Meta ODS {$targetCode}",
                        'active' => true,
                    ],
                );

                OdsIndicator::query()->updateOrCreate(
                    ['code' => $indicatorCode],
                    [
                        'ods_target_id' => $target->id,
                        'name' => trim((string) ($row['Titulo'] ?? $indicatorCode)),
                        'description' => trim((string) ($row['Definicion'] ?? '')) ?: null,
                        'source' => trim((string) ($row['Fuente'] ?? '')) ?: null,
                        'csv_url' => trim((string) ($row['csvUrl'] ?? '')) ?: null,
                        'excel_url' => trim((string) ($row['excelUrl'] ?? '')) ?: null,
                        'imported_at' => $now,
                        'active' => true,
                    ],
                );

                $touchedGoals[$goalCode] = true;
                $touchedTargets[$targetCode] = true;
                $touchedIndicators++;
            }

            return [
                'goals' => count($touchedGoals),
                'targets' => count($touchedTargets),
                'indicators' => $touchedIndicators,
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchIndicators(string $url): array
    {
        $response = Http::timeout(45)
            ->retry(2, 500)
            ->accept('*/*')
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Sinergia respondió HTTP {$response->status()}.");
        }

        return $this->parseIndicatorsScript($response->body());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseIndicatorsScript(string $script): array
    {
        if (! preg_match('/var\s+indicadoresData\s*=\s*(\[.*\])\s*;?\s*$/s', trim($script), $matches)) {
            throw new RuntimeException('No se encontró la variable indicadoresData en el script de Sinergia.');
        }

        $decoded = json_decode($matches[1], true);

        if (! is_array($decoded)) {
            throw new RuntimeException('No fue posible leer indicadoresData como JSON válido.');
        }

        return array_values(array_filter($decoded, is_array(...)));
    }

    private function targetCodeFromIndicator(string $indicatorCode, string $goalCode): string
    {
        $parts = explode('.', strtoupper($indicatorCode));

        if (count($parts) >= 2 && $parts[0] !== '' && $parts[1] !== '') {
            return "{$parts[0]}.{$parts[1]}";
        }

        return "{$goalCode}.0";
    }
}
