<?php

namespace App\Console\Commands;

use App\Enums\EstadoSincronizacionCapa;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use App\Services\InteligenciaGeografica\Conectores\CatalogoCapasAbiertas;
use App\Services\InteligenciaGeografica\SincronizarCapa;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class SincronizarInteligenciaCommand extends Command
{
    protected $signature = 'inteligencia:sincronizar
        {capa? : Código de la capa. Si se omite, solo corre las capas cuya cadencia ya venció}
        {--forzar : Ignora la cadencia y sincroniza todas las capas abiertas}';

    protected $description = 'Descarga las capas abiertas de inteligencia geográfica y registra la bitácora de la fuente';

    public function handle(CatalogoCapasAbiertas $catalogo, SincronizarCapa $sincronizar): int
    {
        $codigo = $this->argument('capa');
        $codigos = $catalogo->codigos();

        if (is_string($codigo) && $codigo !== '' && ! in_array($codigo, $codigos, true)) {
            $this->error($codigo.' no tiene conector externo. Los estudios de suelos son cargue propio.');

            return self::FAILURE;
        }

        $capas = Capa::query()
            ->when(is_string($codigo) && $codigo !== '', fn ($consulta) => $consulta->where('codigo', $codigo))
            ->whereIn('codigo', $codigos)
            ->orderBy('codigo')
            ->get();

        if ($capas->isEmpty()) {
            $this->error('No hay capas del catálogo para sincronizar. Ejecute el seeder de inteligencia geográfica.');

            return self::FAILURE;
        }

        $forzar = (bool) $this->option('forzar') || (is_string($codigo) && $codigo !== '');
        $huboFallo = false;
        $aislar = $this->debeAislar($codigo);

        foreach ($capas as $capa) {
            if (! $forzar && ! $capa->revisionCadenciaVencida()) {
                $this->line($capa->codigo.': dentro de la cadencia, no se consulta la fuente.');

                continue;
            }

            if ($aislar) {
                if (! $this->correrAislada($capa, $sincronizar)) {
                    $huboFallo = true;
                }

                continue;
            }

            try {
                $corrida = $sincronizar->ejecutar($capa);
            } catch (InvalidArgumentException $excepcion) {
                $this->error($capa->codigo.': '.$excepcion->getMessage());
                $huboFallo = true;

                continue;
            } catch (Throwable $excepcion) {
                $this->error($capa->codigo.': '.$excepcion->getMessage());
                $sincronizar->marcarTiempoAgotado($capa, $excepcion->getMessage());
                $huboFallo = true;

                continue;
            }

            $this->informar($corrida);

            if (in_array($corrida->estado, [EstadoSincronizacionCapa::Fallido, EstadoSincronizacionCapa::FuenteNoDisponible], true)) {
                $huboFallo = true;
            }
        }

        return $huboFallo ? self::FAILURE : self::SUCCESS;
    }

    private function debeAislar(mixed $codigo): bool
    {
        return ! app()->runningUnitTests() && (! is_string($codigo) || $codigo === '');
    }

    private function correrAislada(Capa $capa, SincronizarCapa $sincronizar): bool
    {
        $limite = max(1, (int) config('inteligencia.http.capa_timeout'));
        $proceso = new Process([
            PHP_BINARY,
            base_path('artisan'),
            'inteligencia:sincronizar',
            $capa->codigo,
            '--no-interaction',
        ], base_path());
        $proceso->setTimeout($limite);

        try {
            $proceso->run(function (string $tipo, string $salida): void {
                $this->output->write($salida);
            });
        } catch (ProcessTimedOutException) {
            $proceso->stop(3);
            $sincronizar->marcarTiempoAgotado($capa);
            $this->error($capa->codigo.': se agotó el tiempo máximo de '.$limite.' s. Quedó como fuente no disponible.');

            return false;
        }

        return $proceso->isSuccessful();
    }

    private function informar(CapaSincronizacion $corrida): void
    {
        $duracion = $corrida->iniciado_at && $corrida->finalizado_at
            ? ((int) $corrida->iniciado_at->diffInSeconds($corrida->finalizado_at)).' s'
            : '—';
        $corte = $corrida->fecha_corte_detectada?->timezone('UTC')->format('Y-m-d H:i:s') ?? 'sin fecha de corte';
        $capa = $corrida->capa;

        $this->line(sprintf(
            '%s: %s, agregadas %d, actualizadas %d, eliminadas %d, corte %s, %s. %s',
            $capa?->codigo ?? 'capa',
            $corrida->estado->label(),
            $corrida->entidades_agregadas,
            $corrida->entidades_actualizadas,
            $corrida->entidades_eliminadas,
            $corte,
            $duracion,
            $corrida->mensaje_error ?: ($corrida->nota ?: ''),
        ));
    }
}
