<?php

namespace App\Jobs;

use App\Models\InvestmentSyncRun;
use App\Services\Investments\InvestmentSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncInvestmentDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 7200;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $runId)
    {
        $this->onQueue('investments');
    }

    /**
     * Execute the job.
     */
    public function handle(InvestmentSyncService $service): void
    {
        $service->sync(InvestmentSyncRun::findOrFail($this->runId));
    }
}
