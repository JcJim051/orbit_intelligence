<?php

namespace App\Console\Commands;

use App\Models\IndicadorTerritorial;
use App\Models\Indicator;
use App\Models\MicrositioIndicador;
use App\Models\SiidIndicador;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('siid:migrar-publicaciones {--apply : Guarda la transición; sin esta opción solo diagnostica}')]
#[Description('Traslada la configuración editorial de los módulos experimentales al catálogo SIID sincronizado')]
class MigrarPublicacionesSiid extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $matched = 0;
        $unmatched = [];

        MicrositioIndicador::query()->with('dashboard')->orderBy('id')->each(function (MicrositioIndicador $legacy) use ($apply, &$matched, &$unmatched): void {
            $code = $this->normalizeCode($legacy->codigo);
            $indicator = SiidIndicador::query()->where('codigo', $code)->first();
            if (! $indicator) {
                $unmatched[] = ['MicrositioIndicador', $legacy->codigo, $legacy->nombre];

                return;
            }
            $matched++;
            if ($apply) {
                $indicator->publicacion()->updateOrCreate([], [
                    'visible' => (bool) $legacy->es_publico && (bool) $indicator->activo,
                    'etiqueta' => $legacy->nombre,
                    'orden' => (int) $legacy->orden,
                    'destacado' => false,
                    'tablero_tipo' => filled($legacy->dashboard_uuid) ? 'superset' : null,
                    'tablero_referencia' => $legacy->dashboard_uuid,
                    'publicado_at' => $legacy->es_publico ? now() : null,
                ]);
            }
        });

        Indicator::query()->orderBy('id')->each(function (Indicator $legacy) use (&$matched, &$unmatched): void {
            $code = $this->normalizeCode((string) data_get($legacy->public_metadata, 'codigo', ''));
            if ($code === '' || ! SiidIndicador::query()->where('codigo', $code)->exists()) {
                $unmatched[] = ['Indicator', $code ?: 'Sin código', $legacy->name];

                return;
            }
            $matched++;
        });

        if ($apply) {
            IndicadorTerritorial::query()->withTrashed()->each(fn (IndicadorTerritorial $indicator) => $indicator->publicacion()->firstOrCreate([], ['visible' => false]));
        }

        $this->info(($apply ? 'Migración' : 'Diagnóstico').' terminado: '.$matched.' correspondencias.');
        if ($unmatched !== []) {
            $this->warn(count($unmatched).' registros quedaron ocultos para revisión.');
            $this->table(['Origen', 'Código', 'Nombre'], $unmatched);
        }

        return self::SUCCESS;
    }

    private function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/\s+/', '', trim($code)));
    }
}
