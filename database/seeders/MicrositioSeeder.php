<?php

namespace Database\Seeders;

use App\Models\MicrositioCifra;
use App\Models\MicrositioHero;
use App\Models\MicrositioIndicador;
use App\Models\MicrositioMapa;
use App\Models\MicrositioMenu;
use App\Models\MicrositioPie;
use App\Models\MicrositioPublicacion;
use App\Models\MicrositioTema;
use App\Services\Micrositio\PublicadorMicrositio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Contenido del micrositio aprobado. Las cifras y el boletín salen de la pasiva
 * de Hacienda con corte 31/08/2026. Las filas marcadas como ejemplo no son datos oficiales.
 */
class MicrositioSeeder extends Seeder
{
    public function run(): void
    {
        if (MicrositioHero::query()->exists()) {
            return;
        }

        Storage::disk('local')->put(
            'micrositio/banner/banner.svg',
            (string) file_get_contents(public_path('img/micrositio/img/banner.svg')),
        );
        Storage::disk('local')->put(
            'micrositio/publicaciones/pasiva_agosto_2026_sector_fuente.csv',
            (string) file_get_contents(database_path('seeders/data/pasiva_agosto_2026_sector_fuente.csv')),
        );

        MicrositioHero::query()->create([
            'eyebrow' => 'Sistema Integral de Información Departamental',
            'titulo' => 'Gerencia de Información y Estudios Económicos',
            'subtitulo' => 'Datos abiertos, indicadores y estudios que convierten la información del Meta en evidencia para planear, decidir y hacer seguimiento a la gestión pública.',
            'fuentes_linea' => 'Fuentes: Sistema Integral de Información Departamental (SIID) · SSPP · DANE · MinEducación. Corte: 13/11/2026.',
            'pildoras' => [
                ['texto' => 'Indicadores', 'url' => '/micrositio/indicadores'],
                ['texto' => 'Políticas públicas', 'url' => '/micrositio/politicas-publicas'],
                ['texto' => 'Publicaciones', 'url' => '/micrositio/publicaciones'],
                ['texto' => 'Visor geográfico', 'url' => '/micrositio/geovisores'],
            ],
            'banner_path' => 'micrositio/banner/banner.svg',
            'usar_imagen_fondo' => false,
        ]);

        foreach ([
            ['$3,95 billones', 'Presupuesto definitivo de inversión y funcionamiento (pasiva)', 'Ejecución presupuestal acumulada – Secretaría de Hacienda', false],
            ['33,0 %', 'Pagado sobre definitivo ($1,30 billones)', 'Ejecución presupuestal acumulada – Secretaría de Hacienda', false],
            ['56,7 %', 'Participación del SGR en el definitivo ($2,24 billones)', 'Ejecución presupuestal + catálogo de fuentes', false],
            ['15,3 %', 'Participación del SGP en el definitivo ($603 mil millones)', 'Ejecución presupuestal + catálogo de fuentes', false],
        ] as $orden => [$valor, $descripcion, $fuente, $ejemplo]) {
            MicrositioCifra::query()->create([
                'valor' => $valor,
                'origen' => 'manual',
                'descripcion' => $descripcion,
                'fuente' => $fuente,
                'fecha_corte' => '2026-08-31',
                'es_ejemplo' => $ejemplo,
                'orden' => $orden,
            ]);
        }

        if (MicrositioCifra::query()->whereIn('origen', ['indicadores_sectoriales', 'indicadores_territoriales', 'politicas_publicas'])->doesntExist()) {
            foreach ([
                ['203', 'indicadores_sectoriales', 'Indicadores Sectoriales', 'Indicadores sectoriales activos del listado maestro', 'SIID — indicadores activos'],
                ['154', 'indicadores_territoriales', 'Indicadores Territoriales (Propios)', 'Indicadores territoriales propios activos', 'Indicadores territoriales activos'],
                ['18', 'politicas_publicas', 'Políticas Públicas', 'Políticas públicas publicadas, sin ejemplos', 'Micrositio — políticas publicadas'],
            ] as $indice => [$valor, $origen, $etiqueta, $descripcion, $fuente]) {
                MicrositioCifra::query()->create([
                    'valor' => $valor,
                    'origen' => $origen,
                    'criterio' => 'activos',
                    'etiqueta' => $etiqueta,
                    'descripcion' => $descripcion,
                    'fuente' => $fuente,
                    'fecha_corte' => '2026-11-13',
                    'es_ejemplo' => false,
                    'orden' => 10 + $indice,
                ]);
            }
        }

        $temas = [
            'finanzas' => 'Finanzas públicas',
            'pdd' => 'Plan de Desarrollo',
            'territorio' => 'Territorio',
        ];

        $ordenTema = 0;

        foreach ($temas as $slug => $nombre) {
            MicrositioTema::query()->create([
                'slug' => $slug,
                'nombre' => $nombre,
                'orden' => $ordenTema,
            ]);
            $ordenTema++;
        }

        $temaId = MicrositioTema::query()->pluck('id', 'slug');

        foreach ([
            ['FP-01', 'Ejecución de la pasiva por fuente y sector', 'finanzas', 'Definitivo, comprometido, obligado y pagado por sector, fuente y BPIN.', true, false, 'pasiva'],
            ['PD-01', 'Avance del Plan de Desarrollo en el cuatrienio', 'pdd', 'Programado vs ejecutado por meta, pilar y sector.', true, true, null],
            ['PD-02', 'Avance físico vs financiero', 'pdd', 'Metas que gastan sin avanzar y viceversa.', true, true, null],
            ['PD-03', 'Calidad del reporte de seguimiento', 'pdd', 'Metas en No reportado por dependencia.', false, true, null],
            ['TE-01', 'Maquinaria propia y puntos críticos viales', 'territorio', 'Horas máquina vs puntos críticos por municipio.', true, true, null],
        ] as $orden => [$codigo, $nombre, $tema, $descripcion, $publico, $ejemplo, $conjunto]) {
            MicrositioIndicador::query()->create([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'micrositio_tema_id' => $temaId[$tema],
                'dashboard_uuid' => null,
                'descripcion' => $descripcion,
                'es_publico' => $publico,
                'es_ejemplo' => $ejemplo,
                'conjunto_datos' => $conjunto,
                'orden' => $orden,
            ]);
        }

        MicrositioPublicacion::query()->create([
            'tipo' => 'Boletín',
            'titulo' => 'Boletín de ejecución presupuestal – agosto 2026',
            'fecha' => '2026-09-15',
            'resumen' => 'Análisis de la pasiva por fuente de financiación.',
            'archivo_path' => 'micrositio/publicaciones/pasiva_agosto_2026_sector_fuente.csv',
            'nombre_archivo' => 'pasiva_agosto_2026_sector_fuente.csv',
            'es_ejemplo' => false,
            'orden' => 0,
        ]);
        MicrositioPublicacion::query()->create([
            'tipo' => 'Estudio',
            'titulo' => 'Estudio de caracterización económica municipal',
            'fecha' => '2026-07-01',
            'resumen' => 'Dato de ejemplo.',
            'es_ejemplo' => true,
            'orden' => 1,
        ]);
        MicrositioPublicacion::query()->create([
            'tipo' => 'Infografía',
            'titulo' => 'El Meta en cifras 2026',
            'fecha' => '2026-06-10',
            'resumen' => 'Dato de ejemplo.',
            'es_ejemplo' => true,
            'orden' => 2,
        ]);

        foreach ([
            ['Inicio', '/micrositio', 0],
            ['Indicadores', '/micrositio/indicadores', 1],
            ['Políticas Públicas', '/micrositio/politicas-publicas', 2],
            ['Publicaciones', '/micrositio/publicaciones', 3],
            ['Geovisores', '/micrositio/geovisores', 4],
            ['Análisis geoespacial', '/micrositio/analisis-geoespacial', 5],
            ['Datos abiertos', '/micrositio/datos-abiertos/pasiva.csv', 6],
            ['meta.gov.co', 'https://meta.gov.co', 90],
        ] as [$texto, $url, $orden]) {
            MicrositioMenu::query()->updateOrCreate(['url' => $url], compact('texto', 'orden'));
        }

        MicrositioMapa::query()->create([
            'mostrar_bloque' => true,
            'capa_principal' => 'NBI-2018',
            'capas_adicionales' => ['POB-2026', 'IPM-2018', 'EDU-CN-2024'],
            'periodo_modo' => 'ultimo',
            'paleta' => 'auto',
            'polaridad' => 'auto',
            'texto_tarjeta' => '29 municipios · capital Villavicencio',
            'texto_cta' => 'Explorar en el visor',
            'url_cta' => '/micrositio/geovisores',
        ]);

        MicrositioPie::query()->create([
            'direccion' => 'Carrera 33 # 38-45 El Centro – Plazoleta Los Libertadores, Villavicencio, Meta',
            'horario' => 'Lunes a viernes de 8:00 a.m. a 11:30 a.m. y de 2:00 p.m. a 5:30 p.m.',
            'conmutador' => '+57 608 681 85 00',
            'linea_gratuita' => '01 8000 129 202',
            'anticorrupcion' => '+57 608 6818503',
            'correo' => 'gobernaciondelmeta@meta.gov.co',
            'judiciales' => 'notificacionesjudiciales@meta.gov.co',
        ]);

        $publicador = app(PublicadorMicrositio::class);

        foreach (array_keys($publicador->secciones()) as $seccion) {
            $publicador->publicar($seccion);
        }
    }
}
