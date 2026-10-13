<?php

namespace App\Console\Commands;

use App\Models\InteligenciaGeografica\Capa;
use App\Services\InteligenciaGeografica\Conectores\CatalogoCapasAbiertas;
use App\Services\InteligenciaGeografica\SincronizarCapa;
use Illuminate\Console\Command;
use InvalidArgumentException;

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

        foreach ($capas as $capa) {
            if (! $forzar && ! $capa->revisionCadenciaVencida()) {
                $this->line($capa->codigo.': dentro de la cadencia, no se consulta la fuente.');

                continue;
            }

            try {
                $corrida = $sincronizar->ejecutar($capa);
            } catch (InvalidArgumentException $excepcion) {
                $this->error($excepcion->getMessage());
                $huboFallo = true;

                continue;
            }

            $duracion = $corrida->iniciado_at && $corrida->finalizado_at
                ? ((int) $corrida->iniciado_at->diffInSeconds($corrida->finalizado_at)).' s'
                : '—';
            $corte = $corrida->fecha_corte_detectada?->timezone('UTC')->format('Y-m-d H:i:s') ?? 'sin fecha de corte';
            $this->line(sprintf(
                '%s: %s, agregadas %d, actualizadas %d, eliminadas %d, corte %s, %s. %s',
                $capa->codigo,
                $corrida->estado->label(),
                $corrida->entidades_agregadas,
                $corrida->entidades_actualizadas,
                $corrida->entidades_eliminadas,
                $corte,
                $duracion,
                $corrida->mensaje_error ?: ($corrida->nota ?: ''),
            ));

            if ($corrida->estado->value === 'fallido') {
                $huboFallo = true;
            }
        }

        return $huboFallo ? self::FAILURE : self::SUCCESS;
    }
}
