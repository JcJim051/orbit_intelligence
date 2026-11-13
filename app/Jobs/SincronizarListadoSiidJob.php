<?php

namespace App\Jobs;

use App\Services\Siid\SincronizadorListadoSiid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SincronizarListadoSiidJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly bool $apply = true, public readonly string $origin = 'programado', public readonly ?int $userId = null) {}

    /**
     * Execute the job.
     */
    public function handle(SincronizadorListadoSiid $synchronizer): void
    {
        $synchronizer->execute($this->apply, $this->origin, $this->userId);
    }
}
