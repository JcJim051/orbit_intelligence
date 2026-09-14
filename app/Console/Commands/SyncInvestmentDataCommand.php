<?php

namespace App\Console\Commands;

use App\Jobs\SyncInvestmentDataJob;
use App\Models\InvestmentSyncRun;
use App\Services\Investments\InvestmentSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('investments:sync {--universe=ecosystem : governor, territory or ecosystem} {--limit= : Maximum unique projects} {--queue : Dispatch to the queue} ')]
#[Description('Synchronize public investment data for Meta from official Socrata APIs')]
class SyncInvestmentDataCommand extends Command
{
    public function handle(InvestmentSyncService $service): int
    {
        $universe = (string) $this->option('universe');
        if (! in_array($universe, ['governor', 'territory', 'ecosystem'], true)) {
            $this->error('Universe must be governor, territory or ecosystem.');

            return self::INVALID;
        }
        $limitOption = $this->option('limit');
        $limit = $limitOption === null ? (int) config('investments.default_limit') : (int) $limitOption;
        $run = InvestmentSyncRun::create([
            'universe' => $universe, 'status' => 'pending', 'datasets' => array_keys(config('investments.datasets')),
            'project_limit' => $limit > 0 ? $limit : null,
        ]);

        if ($this->option('queue')) {
            SyncInvestmentDataJob::dispatch($run->id);
            $this->info("Synchronization queued: {$run->id}");

            return self::SUCCESS;
        }

        $result = $service->sync($run);
        $this->info("Synchronization {$result->status}: {$result->projects_touched} projects, {$result->rows_written} rows.");

        return in_array($result->status, ['completed', 'partial'], true) ? self::SUCCESS : self::FAILURE;
    }
}
