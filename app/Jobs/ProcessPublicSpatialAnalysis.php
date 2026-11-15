<?php

namespace App\Jobs;

use App\Enums\ModoAnalisis;
use App\Enums\OrigenGeometriaAnalisis;
use App\Models\PublicSpatialAnalysisSession;
use App\Services\InteligenciaGeografica\CrearAnalisisArea;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessPublicSpatialAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $sessionId) {}

    /**
     * Execute the job.
     */
    public function handle(CrearAnalisisArea $creator): void
    {
        $session = PublicSpatialAnalysisSession::query()->find($this->sessionId);

        if ($session === null || $session->isExpired() || $session->estado === 'listo') {
            return;
        }

        $session->update(['estado' => 'procesando', 'error_publico' => null]);

        try {
            $analysis = $creator->crear(
                null,
                ModoAnalisis::Normal,
                null,
                $session->geojson,
                $session->nombre_archivo,
                null,
                4326,
                OrigenGeometriaAnalisis::tryFrom($session->origen_geometria),
                true,
            );
            $session->update(['estado' => 'listo', 'analisis_area_id' => $analysis->id, 'geojson' => null]);
        } catch (Throwable $exception) {
            report($exception);
            $session->update([
                'estado' => 'fallido',
                'error_publico' => 'No fue posible completar el análisis. Revise el polígono e intente nuevamente.',
            ]);
            throw $exception;
        }
    }
}
