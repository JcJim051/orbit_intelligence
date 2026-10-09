<?php

namespace Database\Seeders;

use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\Fuente;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Catálogo de las 11 capas del informe de inteligencia geográfica.
 * Las URL exactas que no están confirmadas quedan nulas, con el fragmento conocido en observacion.
 */
class InteligenciaGeograficaSeeder extends Seeder
{
    public function run(): void
    {
        if (Capa::query()->getConnection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('InteligenciaGeograficaSeeder requiere PostgreSQL con el esquema inteligencia.');
        }

        $fuentes = [
            ['codigo' => 'IGAC', 'nombre' => 'Instituto Geográfico Agustín Codazzi'],
            ['codigo' => 'PNN', 'nombre' => 'Parques Nacionales Naturales de Colombia'],
            ['codigo' => 'DANE', 'nombre' => 'Departamento Administrativo Nacional de Estadística'],
            ['codigo' => 'CORMACARENA', 'nombre' => 'Corporación para el Desarrollo Sostenible del Área de Manejo Especial La Macarena'],
            ['codigo' => 'ANM', 'nombre' => 'Agencia Nacional de Minería'],
            ['codigo' => 'GOBERNACION_META', 'nombre' => 'Gobernación del Meta'],
            ['codigo' => 'SGC', 'nombre' => 'Servicio Geológico Colombiano'],
            ['codigo' => 'ANT', 'nombre' => 'Agencia Nacional de Tierras'],
            ['codigo' => 'IDEAM', 'nombre' => 'Instituto de Hidrología, Meteorología y Estudios Ambientales'],
            ['codigo' => 'SIID', 'nombre' => 'Sistema Integral de Información Departamental'],
        ];

        foreach ($fuentes as $fuente) {
            Fuente::query()->updateOrCreate(['codigo' => $fuente['codigo']], $fuente);
        }

        $fuenteId = Fuente::query()->pluck('id', 'codigo');
        $pendiente = 'TODO: confirmar URL exacta, campos clave, licencia y fecha de actualización. ';

        $capas = [
            [
                'codigo' => 'IGAC_CATASTRO_R1',
                'nombre' => 'Catastro R1 y avalúo por predio',
                'fuente_id' => $fuenteId['IGAC'],
                'tipo_acceso' => 'descarga',
                'url_servicio' => null,
                'layer_id' => null,
                'endpoints' => null,
                'pregunta' => '¿Qué predios y qué avalúo catastral intersectan el polígono?',
                'observacion' => $pendiente.'Referencia no verificada: servicioscem.igac.gov.co/Geovisor/catastral y el hub datos-abiertos-igac para la descarga masiva.',
            ],
            [
                'codigo' => 'RUNAP_AREAS_PROTEGIDAS',
                'nombre' => 'RUNAP — áreas protegidas',
                'fuente_id' => $fuenteId['PNN'],
                'tipo_acceso' => 'rest',
                'url_servicio' => null,
                'layer_id' => '0',
                'endpoints' => null,
                'pregunta' => '¿El polígono intersecta áreas protegidas registradas en el RUNAP?',
                'observacion' => $pendiente.'Referencia no verificada: mapas.parquesnacionales.gov.co, servicio pnn/runap/MapServer, capa 0.',
            ],
            [
                'codigo' => 'DANE_GRILLA_POBLACION_1KM',
                'nombre' => 'Grilla de población de 1 km',
                'fuente_id' => $fuenteId['DANE'],
                'tipo_acceso' => 'rest',
                'url_servicio' => null,
                'layer_id' => '1',
                'endpoints' => null,
                'pregunta' => '¿Cuánta población estimada hay en la grilla de 1 km que cruza el polígono?',
                'observacion' => $pendiente.'Referencia no verificada: geoportal.dane.gov.co, Grilla_DANE/Serv_Grilla_DANE/MapServer, capa 1.',
            ],
            [
                'codigo' => 'CORMACARENA_POT_CLASIFICACION',
                'nombre' => 'Clasificación del suelo POT Meta',
                'fuente_id' => $fuenteId['CORMACARENA'],
                'tipo_acceso' => 'rest',
                'url_servicio' => null,
                'layer_id' => null,
                'endpoints' => null,
                'pregunta' => '¿Cuál es la clasificación del suelo del POT del Meta en el polígono?',
                'observacion' => $pendiente.'Referencia no verificada: services7.arcgis.com/m7QthefN6swNbXsM, capa POT_Meta_Clasificación.',
            ],
            [
                'codigo' => 'ANM_TITULOS_SOLICITUDES',
                'nombre' => 'Títulos y solicitudes mineras',
                'fuente_id' => $fuenteId['ANM'],
                'tipo_acceso' => 'rest',
                'url_servicio' => null,
                'layer_id' => null,
                'endpoints' => [
                    ['nombre' => 'Títulos mineros', 'layer_id' => '4', 'url_servicio' => null],
                    ['nombre' => 'Solicitudes mineras', 'layer_id' => '2', 'url_servicio' => null],
                ],
                'pregunta' => '¿Hay títulos o solicitudes mineras que intersectan el polígono?',
                'observacion' => $pendiente.'Referencia no verificada: geo.anm.gov.co/webgis/rest/services/ANM/ServiciosANM/MapServer, capas 4 (títulos) y 2 (solicitudes).',
            ],
            [
                'codigo' => 'META_DRENAJE',
                'nombre' => 'Drenaje sencillo y doble',
                'fuente_id' => $fuenteId['GOBERNACION_META'],
                'tipo_acceso' => 'rest',
                'url_servicio' => null,
                'layer_id' => null,
                'endpoints' => [
                    ['nombre' => 'Drenaje sencillo', 'layer_id' => 'Drenaje_Sencillo', 'url_servicio' => null],
                    ['nombre' => 'Drenaje doble', 'layer_id' => 'Drenaje_Doble', 'url_servicio' => null],
                ],
                'pregunta' => '¿Qué drenajes sencillos o dobles cruzan el polígono?',
                'observacion' => $pendiente.'Referencia no verificada: services1.arcgis.com/4RHAS9CFLo4QG7AM, capas Drenaje_Sencillo y Drenaje_Doble.',
            ],
            [
                'codigo' => 'SGC_AMENAZA_MOVIMIENTOS_MASA',
                'nombre' => 'Amenaza por movimientos en masa 1:100.000',
                'fuente_id' => $fuenteId['SGC'],
                'tipo_acceso' => 'rest',
                'url_servicio' => null,
                'layer_id' => '15',
                'endpoints' => null,
                'pregunta' => '¿Qué nivel de amenaza por movimientos en masa a escala 1:100.000 cubre el polígono?',
                'observacion' => $pendiente.'Referencia no verificada: srvags.sgc.gov.co, SIMMA/CapasTematicasXEscalas/MapServer, capa 15.',
            ],
            [
                'codigo' => 'ANT_RESGUARDOS_CONSEJOS',
                'nombre' => 'Resguardos indígenas y consejos comunitarios',
                'fuente_id' => $fuenteId['ANT'],
                'tipo_acceso' => 'rest',
                'url_servicio' => null,
                'layer_id' => null,
                'endpoints' => null,
                'pregunta' => '¿El polígono intersecta resguardos indígenas o consejos comunitarios?',
                'observacion' => $pendiente.'Fuente ANT. No hay URL ni identificador de capa confirmados.',
            ],
            [
                'codigo' => 'IDEAM_COBERTURA_TIERRA_2024',
                'nombre' => 'Cobertura de la tierra 2024',
                'fuente_id' => $fuenteId['IDEAM'],
                'tipo_acceso' => 'rest',
                'url_servicio' => null,
                'layer_id' => '0',
                'endpoints' => null,
                'pregunta' => '¿Qué coberturas de la tierra del periodo 2024 cubren el polígono?',
                'observacion' => $pendiente.'Referencia no verificada: visualizador.ideam.gov.co, Cobertura_de_la_tierra_100K_Periodo_2024_limite_administrativo/MapServer, capa 0.',
            ],
            [
                'codigo' => 'CORMACARENA_ECOSISTEMAS',
                'nombre' => 'Ecosistemas estratégicos',
                'fuente_id' => $fuenteId['CORMACARENA'],
                'tipo_acceso' => 'rest',
                'url_servicio' => null,
                'layer_id' => null,
                'endpoints' => null,
                'pregunta' => '¿El polígono intersecta ecosistemas estratégicos?',
                'observacion' => $pendiente.'Fuente Cormacarena. No hay URL ni identificador de capa confirmados.',
            ],
            [
                'codigo' => 'SIID_ESTUDIOS_SUELOS',
                'nombre' => 'Estudios de suelos georreferenciados',
                'fuente_id' => $fuenteId['SIID'],
                'tipo_acceso' => 'cargue_propio',
                'url_servicio' => null,
                'layer_id' => null,
                'endpoints' => null,
                'pregunta' => '¿Qué suelo y qué nivel freático se han encontrado cerca del polígono?',
                'observacion' => 'Cargue propio del SIID (esquema suelos). No consume un servicio externo. TODO: confirmar licencia de publicación de los estudios validados.',
            ],
        ];

        foreach ($capas as $capa) {
            Capa::query()->updateOrCreate(
                ['codigo' => $capa['codigo']],
                [
                    ...$capa,
                    'campos_clave' => null,
                    'licencia' => null,
                    'fecha_actualizacion' => null,
                    'activa' => true,
                ],
            );
        }
    }
}
