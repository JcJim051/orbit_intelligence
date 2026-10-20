<?php

namespace App\Console\Commands;

use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Services\InteligenciaGeografica\InformePdf;
use App\Services\InteligenciaGeografica\MapaBaseAnalisis;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inteligencia:regenerar-mapas {analisis? : Id del análisis. Si se omite, regenera todos}')]
#[Description('Vuelve a componer los mapas de OpenStreetMap guardados en los análisis de área')]
class RegenerarMapasAnalisisCommand extends Command
{
    public function handle(MapaBaseAnalisis $mapas, InformePdf $informe): int
    {
        $id = $this->argument('analisis');
        $consulta = AnalisisArea::query()->orderBy('id');

        if (is_string($id) && $id !== '') {
            $consulta->whereKey($id);
        }

        $analisis = $consulta->get();

        if ($analisis->isEmpty()) {
            $this->error(is_string($id) && $id !== ''
                ? 'No existe el análisis '.$id.'.'
                : 'No hay análisis para regenerar.');

            return self::FAILURE;
        }

        foreach ($analisis as $area) {
            $mapas->olvidar($area);
            $informe->html($area);
            $this->line('Mapas regenerados del análisis '.$area->id.'.');
        }

        return self::SUCCESS;
    }
}
