<?php

namespace App\Services\Investments;

use App\Models\InvestmentBeneficiary;
use App\Models\InvestmentContract;
use App\Models\InvestmentEntityAssignment;
use App\Models\InvestmentFinancial;
use App\Models\InvestmentLocation;
use App\Models\InvestmentPolicyFocus;
use App\Models\InvestmentProduct;
use App\Models\InvestmentProgressReport;
use App\Models\InvestmentProject;
use App\Models\InvestmentSourceSnapshot;
use App\Models\InvestmentSyncRun;
use App\Models\InvestmentTerritorialResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvestmentSyncService
{
    public function __construct(
        private readonly SocrataClient $socrata,
        private readonly InvestmentEntityClassifier $entityClassifier,
    ) {}

    public function sync(InvestmentSyncRun $run): InvestmentSyncRun
    {
        $run->update(['status' => 'running', 'started_at' => now(), 'error_message' => null]);

        try {
            $limit = $run->project_limit;
            $seedRows = $this->discoverUniverse($run, $limit);
            $bpins = $seedRows->pluck('bpin')->filter()->unique()->values();

            foreach ($seedRows as $seed) {
                $this->upsertSeedProject($seed, $run->universe);
            }

            $this->syncBpinDataset($run, 'basic', $bpins, fn (array $row) => $this->upsertBasic($row, $run->universe));
            $this->syncBpinDataset($run, 'financial', $bpins, fn (array $row) => $this->upsertFinancial($row, 'v4ap-cvae'));
            $this->syncBpinDataset($run, 'progress', $bpins, fn (array $row) => $this->upsertProgress($row));
            $this->syncBpinDataset($run, 'locations', $bpins, fn (array $row) => $this->upsertLocation($row));
            $this->syncBpinDataset($run, 'beneficiaries', $bpins, fn (array $row) => $this->upsertBeneficiary($row));
            $this->syncBpinDataset($run, 'products', $bpins, fn (array $row) => $this->upsertProduct($row));
            $this->syncBpinDataset($run, 'regionalized', $bpins, fn (array $row) => $this->upsertRegionalized($row));
            $this->syncBpinDataset($run, 'contracts', $bpins, fn (array $row) => $this->upsertContract($row));
            $this->syncBpinDataset($run, 'policies', $bpins, fn (array $row) => $this->upsertPolicy($row));
            $this->syncBpinDataset($run, 'sgr', $bpins, fn (array $row) => $this->upsertSgr($row, $run->universe));
            $this->recordUnsupportedDataset($run, 'product_locations', 'La API no publica BPIN; no se enlazan filas para evitar asociaciones falsas.');
            $this->syncTerritorialResources($run);

            foreach ($bpins as $bpin) {
                $project = InvestmentProject::where('bpin', $bpin)->first();
                if ($project) {
                    $this->entityClassifier->classify($project);
                }
            }

            $totals = $run->snapshots()->selectRaw('sum(rows_received) as received, sum(rows_written) as written')->first();
            $hasErrors = $run->snapshots()->where('status', 'error')->exists();
            $projectIds = InvestmentProject::whereIn('bpin', $bpins)->pluck('id');
            $confirmed = InvestmentEntityAssignment::whereIn('investment_project_id', $projectIds)->where('status', 'confirmed')->distinct()->count('investment_project_id');
            $suggested = InvestmentEntityAssignment::whereIn('investment_project_id', $projectIds)->where('status', 'suggested')->distinct()->count('investment_project_id');
            $run->update([
                'status' => $hasErrors ? 'partial' : 'completed',
                'finished_at' => now(),
                'rows_received' => (int) ($totals->received ?? 0),
                'rows_written' => (int) ($totals->written ?? 0),
                'projects_touched' => $bpins->count(),
                'classification_confirmed' => $confirmed,
                'classification_suggested' => $suggested,
                'classification_unclassified' => max(0, $projectIds->count() - InvestmentEntityAssignment::whereIn('investment_project_id', $projectIds)->whereIn('status', ['confirmed', 'suggested'])->distinct()->count('investment_project_id')),
            ]);
        } catch (Throwable $exception) {
            $run->update(['status' => 'error', 'finished_at' => now(), 'error_message' => $exception->getMessage()]);
            report($exception);
        }

        return $run->fresh('snapshots');
    }

    /** @return Collection<int, array<string, mixed>> */
    private function discoverUniverse(InvestmentSyncRun $run, ?int $limit): Collection
    {
        $seeds = collect();
        $sourceCount = $run->universe === 'governor' ? 2 : ($run->universe === 'territory' ? 3 : 5);
        $perSourceLimit = $limit === null ? null : max(1, (int) ceil($limit / $sourceCount));

        if (in_array($run->universe, ['governor', 'ecosystem'], true)) {
            $rows = $this->captureRows($run, 'basic', "codigoentidadresponsable='50' OR upper(entidadresponsable)='META'", $perSourceLimit);
            $seeds->push(...$rows->map(fn (array $row): array => $this->seed($row, 'bpin', true, false)));
            $startYear = (int) config('investments.government_period.start_year');
            $endYear = (int) config('investments.government_period.end_year');
            $financials = $this->captureRows($run, 'financial', "codigoentidadresponsable='50' AND vigencia between '{$startYear}' and '{$endYear}'", $perSourceLimit);
            $seeds->push(...$financials->map(fn (array $row): array => $this->seed($row, 'bpin', true, false)));
        }

        if (in_array($run->universe, ['territory', 'ecosystem'], true)) {
            $locations = $this->captureRows($run, 'locations', "codigodepartamento='50' OR upper(departamento)='META'", $perSourceLimit);
            $seeds->push(...$locations->map(fn (array $row): array => $this->seed($row, 'bpin', false, true)));
            $beneficiaries = $this->captureRows($run, 'beneficiaries', "upper(departamento)='META'", $perSourceLimit);
            $seeds->push(...$beneficiaries->map(fn (array $row): array => $this->seed($row, 'bpin', false, true)));
            $sgr = $this->captureRows($run, 'sgr', "upper(departamento)='META'", $perSourceLimit);
            $seeds->push(...$sgr->map(fn (array $row): array => $this->seed($row, 'codigobpin', false, true)));
        }

        return $seeds->filter(fn (array $row): bool => $row['bpin'] !== '')
            ->groupBy('bpin')
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $first['is_governor_meta'] = $rows->contains('is_governor_meta', true);
                $first['is_territory_meta'] = $rows->contains('is_territory_meta', true);

                return $first;
            })->values()->when($limit, fn (Collection $rows) => $rows->take($limit));
    }

    /** @return array<string, mixed> */
    private function seed(array $row, string $bpinKey, bool $governor, bool $territory): array
    {
        return [
            'bpin' => trim((string) ($row[$bpinKey] ?? '')),
            'name' => $row['nombreproyecto'] ?? $row['nombre_del_proyecto'] ?? $row['nombre'] ?? null,
            'sector' => $row['sector'] ?? $row['sectorproyecto'] ?? null,
            'responsible_entity' => $row['entidadresponsable'] ?? null,
            'responsible_entity_code' => $row['codigoentidadresponsable'] ?? null,
            'is_governor_meta' => $governor,
            'is_territory_meta' => $territory,
            'raw_data' => $row,
        ];
    }

    private function upsertSeedProject(array $seed, string $universe): InvestmentProject
    {
        $project = InvestmentProject::firstOrNew(['bpin' => $seed['bpin']]);
        foreach (['name', 'sector', 'responsible_entity', 'responsible_entity_code'] as $field) {
            if (blank($project->{$field}) && filled($seed[$field])) {
                $project->{$field} = $seed[$field];
            }
        }
        $project->is_governor_meta = $project->is_governor_meta || $seed['is_governor_meta'];
        $project->is_territory_meta = $project->is_territory_meta || $seed['is_territory_meta'];
        $project->is_ecosystem_meta = true;
        $project->last_synced_at = now();
        $project->raw_data ??= $seed['raw_data'];
        $project->save();

        return $project;
    }

    private function syncBpinDataset(InvestmentSyncRun $run, string $key, Collection $bpins, callable $writer): void
    {
        $dataset = config("investments.datasets.{$key}");
        $snapshot = $this->startSnapshot($run, $key);
        $received = 0;
        $written = 0;

        try {
            foreach ($bpins->chunk(40) as $chunk) {
                $quoted = $chunk->map(fn (string $bpin): string => "'".str_replace("'", "''", $bpin)."'")->implode(',');
                $field = $dataset['bpin'];
                foreach ($this->socrata->rows($dataset['id'], "{$field} in ({$quoted})") as $row) {
                    $received++;
                    $writer($row);
                    $written++;
                }
            }
            $snapshot->update(['status' => 'completed', 'rows_received' => $received, 'rows_written' => $written]);
        } catch (Throwable $exception) {
            $snapshot->update(['status' => 'error', 'rows_received' => $received, 'rows_written' => $written, 'error_message' => $exception->getMessage()]);
            Log::warning('Investment dataset sync failed', ['dataset' => $dataset['id'], 'run' => $run->id, 'exception' => $exception]);
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function captureRows(InvestmentSyncRun $run, string $key, string $where, ?int $limit): Collection
    {
        $dataset = config("investments.datasets.{$key}");
        $snapshot = $this->startSnapshot($run, $key);

        try {
            $rows = collect(iterator_to_array($this->socrata->rows($dataset['id'], $where, $limit)));
            $snapshot->update(['status' => 'completed', 'rows_received' => $rows->count(), 'rows_written' => 0]);

            return $rows;
        } catch (Throwable $exception) {
            $snapshot->update(['status' => 'error', 'error_message' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function startSnapshot(InvestmentSyncRun $run, string $key): InvestmentSourceSnapshot
    {
        $dataset = config("investments.datasets.{$key}");
        $metadata = [];
        $cutoff = null;
        try {
            $metadata = $this->socrata->metadata($dataset['id']);
            $cutoff = isset($metadata['rowsUpdatedAt']) ? CarbonImmutable::createFromTimestamp((int) $metadata['rowsUpdatedAt']) : null;
        } catch (Throwable $exception) {
            Log::notice('Could not read Socrata metadata', ['dataset' => $dataset['id'], 'message' => $exception->getMessage()]);
        }

        return InvestmentSourceSnapshot::create([
            'investment_sync_run_id' => $run->id,
            'dataset_id' => $dataset['id'],
            'dataset_name' => $metadata['name'] ?? $dataset['name'],
            'source_url' => rtrim((string) config('investments.socrata_base_url'), '/').'/resource/'.$dataset['id'].'.json',
            'status' => 'running',
            'queried_at' => now(),
            'cutoff_at' => $cutoff,
            'metadata' => Arr::only($metadata, ['description', 'rowsUpdatedAt', 'publicationDate', 'metadata']),
        ]);
    }

    private function recordUnsupportedDataset(InvestmentSyncRun $run, string $key, string $warning): void
    {
        $this->startSnapshot($run, $key)->update(['status' => 'skipped', 'warning' => $warning]);
    }

    private function syncTerritorialResources(InvestmentSyncRun $run): void
    {
        $dataset = config('investments.datasets.territorial_resources');
        $snapshot = $this->startSnapshot($run, 'territorial_resources');
        $received = 0;
        try {
            foreach ($this->socrata->rows($dataset['id'], "upper(departamento)='META'", $run->project_limit) as $row) {
                $received++;
                InvestmentTerritorialResource::updateOrCreate(
                    ['source_row_hash' => $this->naturalHash($dataset['id'], $row, ['departamento', 'municipio', 'vigencia', 'entidad', 'sector', 'fuentefinanciacion'])],
                    [
                        'source_dataset_id' => $dataset['id'], 'department' => $row['departamento'] ?? null,
                        'municipality' => $row['municipio'] ?? null, 'fiscal_year' => $this->year($row['vigencia'] ?? null),
                        'entity' => $row['entidad'] ?? null, 'entity_type' => $row['tipoentidad'] ?? null,
                        'sector' => $row['sector'] ?? null, 'funding_source' => $row['fuentefinanciacion'] ?? null,
                        'committed_value' => $this->number($row['valorcomprometido'] ?? null),
                        'obligated_value' => $this->number($row['valorobligado'] ?? null),
                        'paid_value' => $this->number($row['valorpagado'] ?? null), 'raw_data' => $row,
                    ]
                );
            }
            $snapshot->update(['status' => 'completed', 'rows_received' => $received, 'rows_written' => $received]);
        } catch (Throwable $exception) {
            $snapshot->update(['status' => 'error', 'rows_received' => $received, 'rows_written' => $received, 'error_message' => $exception->getMessage()]);
        }
    }

    private function upsertBasic(array $row, string $universe): void
    {
        $bpin = trim((string) ($row['bpin'] ?? ''));
        if ($bpin === '') {
            return;
        }
        $project = InvestmentProject::firstOrNew(['bpin' => $bpin]);
        $governor = ($row['codigoentidadresponsable'] ?? null) === '50' || mb_strtoupper(trim((string) ($row['entidadresponsable'] ?? ''))) === 'META';
        $project->fill([
            'name' => $row['nombreproyecto'] ?? $project->name, 'objective' => $row['objetivogeneral'] ?? null,
            'status' => $row['estadoproyecto'] ?? null, 'substatus' => $row['subestadoproyecto'] ?? null,
            'horizon' => $row['horizonte'] ?? null,
            'horizon_start_year' => $this->horizonYears($row['horizonte'] ?? null)[0],
            'horizon_end_year' => $this->horizonYears($row['horizonte'] ?? null)[1],
            'sector' => $row['sector'] ?? null,
            'responsible_entity' => $row['entidadresponsable'] ?? null, 'responsible_entity_code' => $row['codigoentidadresponsable'] ?? null,
            'project_type' => $row['tipoproyecto'] ?? null, 'budget_program' => $row['programapresupuestal'] ?? null,
            'national_development_plan' => $row['plandesarrollonacional'] ?? null,
            'total_value' => $this->number($row['valortotalproyecto'] ?? null), 'current_value' => $this->number($row['valorvigenteproyecto'] ?? null),
            'obligated_value' => $this->number($row['valorobligacionproyecto'] ?? null), 'paid_value' => $this->number($row['valorpagoproyecto'] ?? null),
            'beneficiaries_total' => $this->integer($row['totalbeneficiario'] ?? null),
            'is_governor_meta' => $project->is_governor_meta || $governor, 'is_ecosystem_meta' => true,
            'source_dataset_id' => 'cf9k-55fw', 'last_synced_at' => now(), 'raw_data' => $row,
        ]);
        $project->save();
    }

    private function upsertFinancial(array $row, string $datasetId): void
    {
        $project = $this->projectFor($row['bpin'] ?? null);
        if (! $project) {
            return;
        }
        InvestmentFinancial::updateOrCreate(
            ['source_row_hash' => $this->naturalHash($datasetId, $row, ['bpin', 'vigencia', 'fuentedefinanciaci_n', 'entidadfuentefinanciacion', 'tiporecursofuentefinanciacion'])],
            [
                'investment_project_id' => $project->id, 'source_dataset_id' => $datasetId,
                'fiscal_year' => $this->year($row['vigencia'] ?? null), 'funding_source' => $row['fuentedefinanciaci_n'] ?? $row['fuentefinanciacion'] ?? null,
                'funding_resource_type' => $row['tiporecursofuentefinanciacion'] ?? null, 'funding_entity' => $row['entidadfuentefinanciacion'] ?? null,
                'requested_value' => $this->number($row['valorsolicitado'] ?? null), 'initial_value' => $this->number($row['valorinicial'] ?? null),
                'current_value' => $this->number($row['valorvigente'] ?? null), 'committed_value' => $this->number($row['valorcomprometido'] ?? null),
                'obligated_value' => $this->number($row['valorobligado'] ?? null), 'paid_value' => $this->number($row['valorpagado'] ?? null),
                'raw_data' => $row,
            ]
        );
    }

    private function upsertProgress(array $row): void
    {
        $project = $this->projectFor($row['bpin'] ?? null);
        if (! $project) {
            return;
        }
        $project->update([
            'physical_progress' => $this->number($row['avancefisico'] ?? null),
            'financial_progress' => $this->number($row['avancefinanciero'] ?? null),
            'last_synced_at' => now(),
        ]);
        InvestmentProgressReport::updateOrCreate(
            ['source_row_hash' => $this->naturalHash('7mxf-bp6x', $row, ['bpin', 'avancefisico', 'avancefinanciero', 'valorvigente'])],
            [
                'investment_project_id' => $project->id, 'source_dataset_id' => '7mxf-bp6x',
                'physical_progress' => $this->number($row['avancefisico'] ?? null),
                'financial_progress' => $this->number($row['avancefinanciero'] ?? null),
                'current_value' => $this->number($row['valorvigente'] ?? null), 'raw_data' => $row,
            ]
        );
    }

    private function upsertLocation(array $row, string $datasetId = 'xikz-44ja'): void
    {
        $project = $this->projectFor($row['bpin'] ?? null);
        if (! $project) {
            return;
        }
        $isMeta = ($row['codigodepartamento'] ?? null) === '50' || mb_strtoupper((string) ($row['departamento'] ?? '')) === 'META';
        $project->update(['is_territory_meta' => $project->is_territory_meta || $isMeta, 'is_ecosystem_meta' => true, 'last_synced_at' => now()]);
        InvestmentLocation::updateOrCreate(
            ['source_row_hash' => $this->naturalHash($datasetId, $row, ['bpin', 'codigodepartamento', 'codigomunicipio'])],
            [
                'investment_project_id' => $project->id, 'source_dataset_id' => $datasetId,
                'region_code' => $row['idregion'] ?? null, 'region' => $row['region'] ?? null,
                'department_code' => $row['codigodepartamento'] ?? null, 'department' => $row['departamento'] ?? null,
                'municipality_code' => $row['codigomunicipio'] ?? null, 'municipality' => $row['municipio'] ?? null,
                'is_department_wide' => blank($row['codigomunicipio'] ?? null), 'raw_data' => $row,
            ]
        );
    }

    private function upsertBeneficiary(array $row): void
    {
        $project = $this->projectFor($row['bpin'] ?? null);
        if (! $project) {
            return;
        }
        InvestmentBeneficiary::updateOrCreate(
            ['source_row_hash' => $this->naturalHash('iuc2-3r6h', $row, ['bpin', 'departamento', 'municipio'])],
            [
                'investment_project_id' => $project->id, 'source_dataset_id' => 'iuc2-3r6h',
                'department' => $row['departamento'] ?? null, 'municipality' => $row['municipio'] ?? null,
                'beneficiaries' => $this->integer($row['totalbeneficiario'] ?? null), 'raw_data' => $row,
            ]
        );
    }

    private function upsertProduct(array $row): void
    {
        $project = $this->projectFor($row['bpin'] ?? null);
        if (! $project) {
            return;
        }
        InvestmentProduct::updateOrCreate(
            ['source_row_hash' => $this->naturalHash('8kfp-z3my', $row, ['bpin', 'producto', 'indicador'])],
            [
                'investment_project_id' => $project->id, 'source_dataset_id' => '8kfp-z3my',
                'product' => $row['producto'] ?? null, 'indicator' => $row['indicador'] ?? null,
                'product_unit' => $row['unidadmedidaproducto'] ?? null, 'indicator_unit' => $row['unidadmedidaindicador'] ?? null,
                'quantity' => $this->number($row['cantidad'] ?? null), 'indicator_target' => $this->number($row['metaindicador'] ?? null),
                'indicator_progress' => $this->number($row['avanceindicador'] ?? null), 'product_value' => $this->number($row['valorproducto'] ?? null),
                'raw_data' => $row,
            ]
        );
    }

    private function upsertRegionalized(array $row): void
    {
        $this->upsertFinancial($row, 'u3qu-swda');
        $this->upsertLocation($row, 'u3qu-swda');
    }

    private function upsertContract(array $row): void
    {
        $project = $this->projectFor($row['bpin'] ?? null);
        if (! $project) {
            return;
        }
        InvestmentContract::updateOrCreate(
            ['source_row_hash' => $this->naturalHash('uwns-mbwd', $row, ['bpin', 'referenciacontrato', 'documentoproveedor', 'objetodelcontrato'])],
            [
                'investment_project_id' => $project->id, 'source_dataset_id' => 'uwns-mbwd',
                'reference' => $row['referenciacontrato'] ?? null, 'supplier' => $row['proveedor'] ?? null,
                'supplier_document' => $row['documentoproveedor'] ?? null, 'status' => $row['estadocontrato'] ?? null,
                'value' => $this->number($row['valorcontrato'] ?? null), 'object' => $row['objetodelcontrato'] ?? null,
                'process_url' => $row['urlproceso'] ?? null, 'fiscal_year' => $this->year($row['vigenciacontrato'] ?? null), 'raw_data' => $row,
            ]
        );
    }

    private function upsertPolicy(array $row): void
    {
        $project = $this->projectFor($row['bpin'] ?? null);
        if (! $project) {
            return;
        }
        InvestmentPolicyFocus::updateOrCreate(
            ['source_row_hash' => $this->naturalHash('yt5q-ekus', $row, ['bpin', 'politicatransversal', 'dimensionpoliticatransversal', 'vigencia', 'mes'])],
            [
                'investment_project_id' => $project->id, 'source_dataset_id' => 'yt5q-ekus',
                'policy' => $row['politicatransversal'] ?? null, 'dimension' => $row['dimensionpoliticatransversal'] ?? null,
                'fiscal_year' => $this->year($row['vigencia'] ?? null), 'month' => $this->integer($row['mes'] ?? null),
                'current_value' => $this->number($row['valorvigente'] ?? null), 'committed_value' => $this->number($row['valorcomprometido'] ?? null),
                'obligated_value' => $this->number($row['valorobligado'] ?? null), 'paid_value' => $this->number($row['valorpagado'] ?? null), 'raw_data' => $row,
            ]
        );
    }

    private function upsertSgr(array $row, string $universe): void
    {
        $bpin = trim((string) ($row['codigobpin'] ?? ''));
        if ($bpin === '') {
            return;
        }
        $project = InvestmentProject::firstOrNew(['bpin' => $bpin]);
        $isMeta = mb_strtoupper(trim((string) ($row['departamento'] ?? ''))) === 'META';
        $project->fill([
            'name' => $project->name ?: ($row['nombre'] ?? null), 'status' => $project->status ?: ($row['estado'] ?? null),
            'sector' => $project->sector ?: ($row['sector'] ?? null), 'executing_entity' => $row['entidadejecutora'] ?? null,
            'total_value' => $project->total_value ?: $this->number($row['valortotal'] ?? null),
            'physical_progress' => $this->number($row['ejecucionfisica'] ?? null), 'financial_progress' => $this->number($row['ejecucionfinanciera'] ?? null),
            'project_type' => $project->project_type ?: 'SGR', 'is_territory_meta' => $project->is_territory_meta || $isMeta,
            'is_ecosystem_meta' => true, 'last_synced_at' => now(),
        ]);
        $project->save();
    }

    private function projectFor(mixed $bpin): ?InvestmentProject
    {
        $value = trim((string) $bpin);

        return $value === '' ? null : InvestmentProject::where('bpin', $value)->first();
    }

    private function naturalHash(string $datasetId, array $row, array $keys): string
    {
        $identity = collect($keys)->mapWithKeys(fn (string $key): array => [$key => trim((string) ($row[$key] ?? ''))])->all();

        return hash('sha256', $datasetId.'|'.json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $normalized = preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', trim((string) $value)));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function integer(mixed $value): ?int
    {
        $number = $this->number($value);

        return $number === null ? null : (int) round($number);
    }

    private function year(mixed $value): ?int
    {
        $year = $this->integer($value);

        return $year !== null && $year >= 1900 && $year <= 2200 ? $year : null;
    }

    /** @return array{0: int|null, 1: int|null} */
    private function horizonYears(mixed $value): array
    {
        preg_match_all('/(?:19|20|21)\d{2}/', (string) $value, $matches);
        $years = collect($matches[0] ?? [])->map(fn (string $year): int => (int) $year);

        return [$years->min(), $years->max()];
    }
}
