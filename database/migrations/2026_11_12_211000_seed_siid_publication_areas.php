<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $ejes = [
            'SOCIODEMOGRAFICO' => ['Sociodemográfico', 10],
            'ECONOMICO' => ['Económico', 20],
            'AMBIENTAL_TERRITORIAL' => ['Ambiental, territorio y vivienda', 30],
        ];
        $areas = [
            'SOCIODEMOGRAFICO' => [
                'Demografía y población', 'Social', 'Salud', 'Educación', 'Gobierno',
                'Pobreza y desigualdad', 'Mercado Laboral',
            ],
            'ECONOMICO' => [
                'Cuentas nacionales', 'Precios', 'Turismo', 'Hidrocarburos', 'Agropecuario',
                'Servicios', 'Empresarial', 'Comercio internacional', 'Finanzas públicas', 'TIC', 'Gobierno',
            ],
            'AMBIENTAL_TERRITORIAL' => ['Ambiental', 'Territorio', 'Vivienda'],
        ];

        DB::transaction(function () use ($ejes, $areas): void {
            foreach ($ejes as $codigo => [$nombre, $orden]) {
                DB::table('siid_publicacion.ejes')->updateOrInsert(
                    ['codigo' => $codigo],
                    ['nombre' => $nombre, 'orden' => $orden, 'activo' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }

            foreach ($areas as $ejeCodigo => $nombres) {
                $ejeId = DB::table('siid_publicacion.ejes')->where('codigo', $ejeCodigo)->value('id');
                foreach ($nombres as $index => $nombre) {
                    DB::table('siid_publicacion.areas_tematicas')->updateOrInsert(
                        ['eje_id' => $ejeId, 'nombre' => $nombre],
                        [
                            'codigo' => Str::upper(Str::slug($nombre, '_')),
                            'slug' => Str::slug($ejeCodigo.'-'.$nombre),
                            'orden' => ($index + 1) * 10,
                            'activo' => true,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }

            $sociodemograficoId = DB::table('siid_publicacion.ejes')->where('codigo', 'SOCIODEMOGRAFICO')->value('id');
            $pobrezaId = DB::table('siid_publicacion.areas_tematicas')
                ->where('eje_id', $sociodemograficoId)
                ->where('nombre', 'Pobreza y desigualdad')
                ->value('id');
            DB::table('siid_publicacion.equivalencias_area')->updateOrInsert(
                ['texto_origen' => 'Pobreza y desilgualdad'],
                [
                    'texto_normalizado' => 'pobreza y desilgualdad',
                    'area_tematica_id' => $pobrezaId,
                    'activo' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        });
    }

    public function down(): void
    {
        // Catálogo compartido: no eliminar datos durante un rollback.
    }
};
