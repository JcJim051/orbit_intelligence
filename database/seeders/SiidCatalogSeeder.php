<?php

namespace Database\Seeders;

use App\Models\Municipio;
use App\Models\SiidAreaTematica;
use App\Models\SiidEje;
use App\Models\SiidEquivalenciaArea;
use App\Models\SiidTerritorio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SiidCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['codigo' => 'SOCIODEMOGRAFICO', 'nombre' => 'Sociodemográfico', 'orden' => 10],
            ['codigo' => 'ECONOMICO', 'nombre' => 'Económico', 'orden' => 20],
            ['codigo' => 'AMBIENTAL_TERRITORIAL', 'nombre' => 'Ambiental, territorio y vivienda', 'orden' => 30],
        ] as $eje) {
            SiidEje::query()->updateOrCreate(['codigo' => $eje['codigo']], $eje + ['activo' => true]);
        }

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

        foreach ($areas as $ejeCodigo => $nombres) {
            $eje = SiidEje::query()->where('codigo', $ejeCodigo)->firstOrFail();
            foreach ($nombres as $index => $nombre) {
                SiidAreaTematica::query()->updateOrCreate(
                    ['eje_id' => $eje->id, 'nombre' => $nombre],
                    [
                        'codigo' => Str::upper(Str::slug($nombre, '_')),
                        'slug' => Str::slug($ejeCodigo.'-'.$nombre),
                        'orden' => ($index + 1) * 10,
                        'activo' => true,
                    ]
                );
            }
        }

        $pobreza = SiidAreaTematica::query()
            ->whereBelongsTo(SiidEje::query()->where('codigo', 'SOCIODEMOGRAFICO')->firstOrFail(), 'eje')
            ->where('nombre', 'Pobreza y desigualdad')
            ->firstOrFail();
        SiidEquivalenciaArea::query()->updateOrCreate(
            ['texto_origen' => 'Pobreza y desilgualdad'],
            ['area_tematica_id' => $pobreza->id, 'activo' => true]
        );

        SiidTerritorio::query()->updateOrCreate(['codigo' => '00'], [
            'nombre' => 'Colombia', 'tipo' => 'pais', 'departamento_codigo' => null, 'activo' => true,
        ]);
        SiidTerritorio::query()->updateOrCreate(['codigo' => '50'], [
            'nombre' => 'Meta', 'tipo' => 'departamento', 'departamento_codigo' => '50', 'activo' => true,
        ]);

        Municipio::query()->where('activo', true)->orderBy('codigo_dane')->each(function (Municipio $municipio): void {
            SiidTerritorio::query()->updateOrCreate(['codigo' => $municipio->codigo_dane], [
                'nombre' => $municipio->nombre,
                'tipo' => 'municipio',
                'departamento_codigo' => '50',
                'activo' => true,
            ]);
        });
    }
}
