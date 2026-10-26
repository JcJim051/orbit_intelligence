<?php

namespace Database\Seeders;

use App\Models\Trazador;
use App\Models\TrazadorCategoria;
use Illuminate\Database\Seeder;
use RuntimeException;

class TrazadorCategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categoriasPorTrazador = [
            'TD-001' => [
                'Infancia - Deporte, recreación, cultura, juego, tecnologia y medio ambiente',
                'Adolescencia - Deporte, recreación, cultura, juego, tecnologia y medio ambiente',
                'Primera Infancia - Deporte, recreación, cultura, juego, tecnologia y medio ambiente',
                'Transversales - Superación de la Pobreza',
                'Transversales - Gobierno y capacidades fortalecidas',
                'Transversales - Tics y virtualización de las atenciones',
                'Adolescencia - Oportunidades para la transición a la juventud',
                'Adolescencia - Educación y formación Integral',
                'Infancia - Educación y formación Integral',
                'Primera Infancia - Educación y formación Integral',
            ],
            'TD-002' => [
                'Educación y acceso a nuevas tecnologías-Desarrollo institucional transformación cultural',
                'Autonomía económica y acceso a activos',
                'Educación y acceso a nuevas tecnologías',
                'Mujer libre de violencias-Desarrollo institucional y transformación cultural',
                'Participación en los escenarios de poder y toma de decisiones-Desarrollo institucional y transformación cultural',
                'Autonomía económica y accesos a activos-Desarrollo institucional y transformación cultural',
            ],
            'TD-003' => [
                '1.Reforma Rural Integral',
                '5.Victimas del conflicto',
                '3.Fin del Conflicto',
            ],
            'TD-004' => [
                'Población Afrocolombiana',
                'Pueblos Indígenas',
            ],
        ];

        foreach ($categoriasPorTrazador as $codigoTrazador => $nombres) {
            $trazador = Trazador::query()->where('codigo', $codigoTrazador)->first();
            if (! $trazador) {
                throw new RuntimeException("No existe el trazador {$codigoTrazador}. Ejecute primero TrazadorSeeder.");
            }

            foreach ($nombres as $nombre) {
                $categoria = TrazadorCategoria::withTrashed()->firstOrNew([
                    'trazador_id' => $trazador->id,
                    'nombre' => $nombre,
                ]);
                $categoria->activo = true;
                $categoria->deleted_at = null;
                $categoria->save();
            }
        }
    }
}
