<?php

namespace App\Console\Commands;

use App\Models\PublicSpatialAnalysisSession;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('geospatial:prune-public')]
#[Description('Elimina sesiones y resultados públicos geoespaciales vencidos.')]
class PrunePublicSpatialAnalyses extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = 0;
        PublicSpatialAnalysisSession::query()
            ->where('expires_at', '<=', now())
            ->with('analysis')
            ->chunkById(100, function ($sessions) use (&$deleted): void {
                foreach ($sessions as $session) {
                    $analysis = $session->analysis;
                    $session->delete();
                    $analysis?->delete();
                    $deleted++;
                }
            });

        $this->info("Sesiones públicas eliminadas: {$deleted}");

        return self::SUCCESS;
    }
}
