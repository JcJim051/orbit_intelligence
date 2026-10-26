<?php

namespace Database\Seeders;

use App\Models\PoliticaPublicaDepartamental;
use Illuminate\Database\Seeder;

class PoliticaPublicaDepartamentalSeeder extends Seeder
{
    public function run(): void
    {
        $politicas = [
            'Política pública de hábitat para las familias del Meta',
            'Política pública para la inclusión de población LGBTI del departamento del Meta',
            'Política pública para la reducción de consumo de sustancias psicoactivas y se declara la política pública de control a la oferta de sustancias psicoactivas en el Departamento',
            'Política pública de empleo emprendimiento y trabajo decente',
            'Política pública discapacidad e inclusión social del departamento del Meta',
            'Política pública de envejecimiento y vejez del departamento del Meta',
            'Política pública de seguridad alimentaria y nutricional para el departamento del Meta',
            'Política pública de derechos humanos, derecho internacional humanitario y paz para el departamento del Meta',
            'Política pública de cultura del departamento del Meta',
            'Política pública de turismo del Meta',
            'Política pública indígena del departamento del Meta',
            'Política pública del deporte, recreación, actividad y educación física - Movernos en la Meta',
            'Política pública integral de libertad religiosa y cultos en el departamento del Meta',
            'Política pública de seguridad vial para el departamento del Meta',
            'Política pública de Internacionalización del departamento del Meta',
            'Ley 1448 de 2011 "Por la cual se dictan medidas de atención, asistencia y reparación integral a las víctimas del conflicto armado interno y se dictan otras disposiciones."',
        ];

        foreach ($politicas as $nombre) {
            $politica = PoliticaPublicaDepartamental::withTrashed()->firstOrNew(['nombre' => $nombre]);
            $politica->activo = true;
            $politica->deleted_at = null;
            $politica->save();
        }
    }
}
