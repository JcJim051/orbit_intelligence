<?php

namespace App\Services\InteligenciaGeografica\Conectores;

/**
 * URLs, licencias y filtros comprobados contra el servicio público.
 * Lo que no respondió una descarga del Meta no se inventa.
 */
class CatalogoCapasAbiertas
{
    /**
     * Envolvente aproximada del Meta (oeste, sur, este, norte) en EPSG:4326.
     * Las capas sin código de departamento se recortan después con el límite departamental.
     *
     * @var array{0: float, 1: float, 2: float, 3: float}
     */
    public const BBOX_META = [-74.95, 1.45, -70.85, 4.95];

    public const LIMITE_DEPARTAMENTAL = 'https://services1.arcgis.com/4RHAS9CFLo4QG7AM/arcgis/rest/services/Límite_Departamental_Meta/FeatureServer/0';

    public const LICENCIA_NO_DECLARADA = 'No declarada en el servicio.';

    public const LICENCIA_NO_DECLARADA_FICHA = 'No declarada en el servicio ni en la ficha del ítem.';

    /**
     * @return list<string>
     */
    public function codigos(): array
    {
        return array_map(fn (DefinicionCapaAbierta $definicion): string => $definicion->codigo, $this->definiciones());
    }

    public function definicion(string $codigo): ?DefinicionCapaAbierta
    {
        foreach ($this->definiciones() as $definicion) {
            if ($definicion->codigo === $codigo) {
                return $definicion;
            }
        }

        return null;
    }

    /**
     * @return list<DefinicionCapaAbierta>
     */
    public function definiciones(): array
    {
        $limite = self::LIMITE_DEPARTAMENTAL;
        $bbox = self::BBOX_META;

        return [
            new DefinicionCapaAbierta(
                codigo: 'IGAC_CATASTRO_R1',
                disponible: true,
                licencia: 'CC BY-SA 4.0',
                cita: 'Instituto Geográfico Agustín Codazzi. Base catastral pública del Meta, terrenos rurales y urbanos. https://www.arcgis.com/sharing/rest/content/items/7209bbf194fa4ece886bb17dd4ec0423. Licencia: CC BY-SA 4.0.',
                observacion: 'No se consulta R_TERRENO: el cortafuegos lo bloquea. La copia sale del paquete 50_META.zip publicado en datos abiertos del IGAC (geodatabase, licencia CC BY-SA 4.0). Cubre los municipios cuyo gestor es el IGAC. Villavicencio (50001) no cubierto: gestor propio.',
                urlServicio: 'https://www.arcgis.com/sharing/rest/content/items/7209bbf194fa4ece886bb17dd4ec0423/data',
                layerId: 'R_TERRENO,U_TERRENO',
                endpoints: [],
                itemUrl: 'https://www.arcgis.com/sharing/rest/content/items/7209bbf194fa4ece886bb17dd4ec0423?f=json',
                capasPaquete: ['R_TERRENO', 'U_TERRENO'],
            ),
            new DefinicionCapaAbierta(
                codigo: 'RUNAP_AREAS_PROTEGIDAS',
                disponible: true,
                licencia: self::LICENCIA_NO_DECLARADA,
                cita: 'Parques Nacionales Naturales de Colombia. Registro Único Nacional de Áreas Protegidas. https://mapas.parquesnacionales.gov.co/arcgis/rest/services/pnn/runap/FeatureServer/0. Licencia: no declarada en el servicio. Copyright: Parques Nacionales Naturales de Colombia.',
                observacion: 'Servicio verificado: mapas.parquesnacionales.gov.co, pnn/runap/FeatureServer/0. No trae código de departamento; se filtra con la envolvente del Meta y se recorta con el límite departamental. La fecha de corte sale del Last-Modified del zip citado en la descripción del servicio.',
                urlServicio: 'https://mapas.parquesnacionales.gov.co/arcgis/rest/services/pnn/runap/FeatureServer/0',
                layerId: '0',
                endpoints: [[
                    'nombre' => 'Registro Único Nacional de Áreas Protegidas',
                    'clave' => 'runap',
                    'url' => 'https://mapas.parquesnacionales.gov.co/arcgis/rest/services/pnn/runap/FeatureServer/0',
                    'where' => '1=1',
                    'campo_id' => 'ap_id',
                    'bbox' => true,
                ]],
                fechaEnZipDeLaDescripcion: true,
                recorteUrl: $limite,
                bbox: $bbox,
            ),
            new DefinicionCapaAbierta(
                codigo: 'DANE_GRILLA_POBLACION_1KM',
                disponible: true,
                licencia: self::LICENCIA_NO_DECLARADA,
                cita: 'Departamento Administrativo Nacional de Estadística. Grilla de población de 1 km, nivel N6_1K. https://geoportal.dane.gov.co/mparcgis/rest/services/Grilla_DANE/Serv_Grilla_DANE/FeatureServer/1. Licencia: no declarada en el servicio. Copyright: Departamento Administrativo Nacional de Estadística DANE.',
                observacion: 'Servicio verificado: geoportal.dane.gov.co/mparcgis, Grilla_DANE/Serv_Grilla_DANE/FeatureServer/1 (N6_1K). Filtro dpto_cod = 50. El servicio no publica fecha de corte en los metadatos; sin esa fecha el análisis no usa la copia.',
                urlServicio: 'https://geoportal.dane.gov.co/mparcgis/rest/services/Grilla_DANE/Serv_Grilla_DANE/FeatureServer/1',
                layerId: '1',
                endpoints: [[
                    'nombre' => 'Grilla N6 de 1 km',
                    'clave' => 'n6',
                    'url' => 'https://geoportal.dane.gov.co/mparcgis/rest/services/Grilla_DANE/Serv_Grilla_DANE/FeatureServer/1',
                    'where' => "dpto_cod='50'",
                    'campo_id' => 'n6_cod',
                    'bbox' => false,
                ]],
            ),
            new DefinicionCapaAbierta(
                codigo: 'CORMACARENA_POT_CLASIFICACION',
                disponible: true,
                licencia: self::LICENCIA_NO_DECLARADA_FICHA,
                cita: 'Cormacarena. Clasificación del suelo POT Meta. https://services7.arcgis.com/m7QthefN6swNbXsM/arcgis/rest/services/Planes_de_Ordenamiento_Territorial_POT_Meta_Clasificación/FeatureServer/0. Licencia: no declarada en el servicio ni en la ficha del ítem.',
                observacion: 'Servicio público verificado en ArcGIS Online de Cormacarena. La capa ya está limitada al Meta. La fecha de corte es dataLastEditDate del servicio.',
                urlServicio: 'https://services7.arcgis.com/m7QthefN6swNbXsM/arcgis/rest/services/Planes_de_Ordenamiento_Territorial_POT_Meta_Clasificación/FeatureServer/0',
                layerId: '0',
                endpoints: [[
                    'nombre' => 'Clasificación POT Meta',
                    'clave' => 'pot',
                    'url' => 'https://services7.arcgis.com/m7QthefN6swNbXsM/arcgis/rest/services/Planes_de_Ordenamiento_Territorial_POT_Meta_Clasificación/FeatureServer/0',
                    'where' => '1=1',
                    'campo_id' => 'OBJECTID',
                    'bbox' => false,
                ]],
                itemUrl: 'https://www.arcgis.com/sharing/rest/content/items/6193a3dfacdd44bc9f10405dce572641?f=json',
            ),
            new DefinicionCapaAbierta(
                codigo: 'ANM_TITULOS_SOLICITUDES',
                disponible: true,
                licencia: self::LICENCIA_NO_DECLARADA,
                cita: 'Agencia Nacional de Minería. Títulos vigentes y solicitudes vigentes. https://geo.anm.gov.co/webgis/rest/services/ANM/ServiciosANM/MapServer. Licencia: no declarada en el servicio. Copyright: ANM,2023.',
                observacion: 'Servicio verificado: geo.anm.gov.co, ANM/ServiciosANM/MapServer, capa 4 Título Vigente y capa 2 Solicitud Vigente. El campo DEPARTAMENTOS distingue mayúsculas: el filtro es Meta, no META. La fecha de corte sale del texto «actualizado el 22/03/2023» de la descripción del servicio.',
                urlServicio: 'https://geo.anm.gov.co/webgis/rest/services/ANM/ServiciosANM/MapServer',
                layerId: null,
                endpoints: [
                    [
                        'nombre' => 'Títulos vigentes',
                        'clave' => 'titulo',
                        'url' => 'https://geo.anm.gov.co/webgis/rest/services/ANM/ServiciosANM/MapServer/4',
                        'where' => "DEPARTAMENTOS LIKE '%Meta%'",
                        'campo_id' => 'CODIGO_EXPEDIENTE',
                        'bbox' => false,
                    ],
                    [
                        'nombre' => 'Solicitudes vigentes',
                        'clave' => 'solicitud',
                        'url' => 'https://geo.anm.gov.co/webgis/rest/services/ANM/ServiciosANM/MapServer/2',
                        'where' => "DEPARTAMENTOS LIKE '%Meta%'",
                        'campo_id' => 'CODIGO_EXPEDIENTE',
                        'bbox' => false,
                    ],
                ],
                descripcionUrl: 'https://geo.anm.gov.co/webgis/rest/services/ANM/ServiciosANM/MapServer?f=json',
                fechaEnDescripcion: true,
            ),
            new DefinicionCapaAbierta(
                codigo: 'META_DRENAJE',
                disponible: true,
                licencia: self::LICENCIA_NO_DECLARADA_FICHA,
                cita: 'Gobernación del Meta. Drenaje sencillo y drenaje doble. https://services1.arcgis.com/4RHAS9CFLo4QG7AM/arcgis/rest/services/Drenaje_Sencillo/FeatureServer/0. Licencia: no declarada en el servicio ni en la ficha del ítem.',
                observacion: 'Servicios públicos verificados en ArcGIS Online de la Gobernación del Meta: Drenaje_Sencillo (líneas) y Drenaje_Doble (polígonos). Ya corresponden al Meta. La fecha de corte es el mayor dataLastEditDate de las dos capas.',
                urlServicio: 'https://services1.arcgis.com/4RHAS9CFLo4QG7AM/arcgis/rest/services/Drenaje_Sencillo/FeatureServer/0',
                layerId: null,
                endpoints: [
                    [
                        'nombre' => 'Drenaje sencillo',
                        'clave' => 'sencillo',
                        'url' => 'https://services1.arcgis.com/4RHAS9CFLo4QG7AM/arcgis/rest/services/Drenaje_Sencillo/FeatureServer/0',
                        'where' => '1=1',
                        'campo_id' => 'FID',
                        'bbox' => false,
                    ],
                    [
                        'nombre' => 'Drenaje doble',
                        'clave' => 'doble',
                        'url' => 'https://services1.arcgis.com/4RHAS9CFLo4QG7AM/arcgis/rest/services/Drenaje_Doble/FeatureServer/0',
                        'where' => '1=1',
                        'campo_id' => 'FID',
                        'bbox' => false,
                    ],
                ],
                itemUrl: 'https://www.arcgis.com/sharing/rest/content/items/0699d926b8da4564a300e3273995a91a?f=json',
            ),
            new DefinicionCapaAbierta(
                codigo: 'SGC_AMENAZA_MOVIMIENTOS_MASA',
                disponible: true,
                licencia: self::LICENCIA_NO_DECLARADA,
                cita: 'Servicio Geológico Colombiano. Zonificación de amenaza por movimientos en masa, escala 100K. https://srvags.sgc.gov.co/arcgis/rest/services/SIMMA/CapasTematicasXEscalas/MapServer/15. Licencia: no declarada en el servicio. Copyright: Servicio geologico Colombiano.',
                observacion: 'Servicio verificado: srvags.sgc.gov.co, SIMMA/CapasTematicasXEscalas/MapServer/15 (capa 100K). El servicio declara supportsPagination en false y cada polígono pesa varios megabytes, así que la descarga pide los identificadores del Meta y luego cada geometría por separado. Si algún identificador no entrega geometría, no se guarda una copia incompleta. No trae departamento; se filtra con la envolvente del Meta y se recorta con el límite departamental. El servicio no publica fecha de corte.',
                urlServicio: 'https://srvags.sgc.gov.co/arcgis/rest/services/SIMMA/CapasTematicasXEscalas/MapServer/15',
                layerId: '15',
                endpoints: [[
                    'nombre' => 'Zonificación 100K',
                    'clave' => 'mm100',
                    'url' => 'https://srvags.sgc.gov.co/arcgis/rest/services/SIMMA/CapasTematicasXEscalas/MapServer/15',
                    'where' => '1=1',
                    'campo_id' => 'OBJECTID',
                    'bbox' => true,
                ]],
                recorteUrl: $limite,
                bbox: $bbox,
            ),
            new DefinicionCapaAbierta(
                codigo: 'ANT_RESGUARDOS_CONSEJOS',
                disponible: true,
                licencia: self::LICENCIA_NO_DECLARADA_FICHA,
                cita: 'Agencia Nacional de Tierras. Territorios colectivos étnicos: resguardos indígenas y consejos comunitarios. https://geoportal.ant.gov.co/servidor/rest/services/LADM/tpc/FeatureServer/0. Licencia: no declarada en el servicio ni en la ficha del ítem.',
                observacion: 'Servicio verificado: geoportal.ant.gov.co, LADM/tpc/FeatureServer/0. La capa reúne resguardos indígenas y consejos comunitarios (campo etiqueta) y no trae departamento. Se filtra con la envolvente del Meta y se recorta con el límite departamental. La fecha de corte es la modificación de la ficha del ítem cuando la capa no trae dataLastEditDate.',
                urlServicio: 'https://geoportal.ant.gov.co/servidor/rest/services/LADM/tpc/FeatureServer/0',
                layerId: '0',
                endpoints: [[
                    'nombre' => 'Territorios colectivos étnicos',
                    'clave' => 'tpc',
                    'url' => 'https://geoportal.ant.gov.co/servidor/rest/services/LADM/tpc/FeatureServer/0',
                    'where' => '1=1',
                    'campo_id' => 'GlobalID',
                    'bbox' => true,
                ]],
                itemUrl: 'https://geoportal.ant.gov.co/portal/sharing/rest/content/items/2996291c51c249fa842ca6c2cce7b8ee?f=json',
                recorteUrl: $limite,
                bbox: $bbox,
            ),
            new DefinicionCapaAbierta(
                codigo: 'IDEAM_COBERTURA_TIERRA_2024',
                disponible: true,
                licencia: 'Dato Abierto',
                cita: 'IDEAM. Cobertura de la tierra 100K, periodo 2024, límite administrativo. Metodología CORINE Land Cover adaptada para Colombia. https://visualizador.ideam.gov.co/gisserver/rest/services/Capascache/Cobertura_de_la_tierra_100K_Periodo_2024_limite_administrativo/FeatureServer/0. Licencia: Dato Abierto.',
                observacion: 'Servicio verificado: visualizador.ideam.gov.co/gisserver, Capascache/Cobertura_de_la_tierra_100K_Periodo_2024_limite_administrativo/FeatureServer/0. Filtro cod_dane_dpto = 50. La licencia «Dato Abierto» está en la ficha del ítem. La fecha de corte es dataLastEditDate o, si no viene, la modificación de esa ficha.',
                urlServicio: 'https://visualizador.ideam.gov.co/gisserver/rest/services/Capascache/Cobertura_de_la_tierra_100K_Periodo_2024_limite_administrativo/FeatureServer/0',
                layerId: '0',
                endpoints: [[
                    'nombre' => 'Cobertura de la tierra 2024',
                    'clave' => 'clc',
                    'url' => 'https://visualizador.ideam.gov.co/gisserver/rest/services/Capascache/Cobertura_de_la_tierra_100K_Periodo_2024_limite_administrativo/FeatureServer/0',
                    'where' => "cod_dane_dpto='50'",
                    'campo_id' => 'objectid',
                    'bbox' => false,
                ]],
                itemUrl: 'https://visualizador.ideam.gov.co/portal/sharing/rest/content/items/2631c70bff5346b8ac84e8b768d67944?f=json',
            ),
            $this->ecosistemas(),
        ];
    }

    private function ecosistemas(): DefinicionCapaAbierta
    {
        $base = 'https://services7.arcgis.com/m7QthefN6swNbXsM/arcgis/rest/services/Ecosistemas_Estrategicos/FeatureServer';
        $nombres = [
            0 => 'Humedales puntuales',
            1 => 'Humedales identificados',
            2 => 'Sabanas inundables',
            3 => 'Páramos jurisdicción Cormacarena',
            4 => 'Otros páramos del departamento',
            5 => 'Áreas protegidas SINAP',
            6 => 'Delimitación áreas artículo 111',
            7 => 'Predios zonas superficiales artículo 111',
            8 => 'Predios zonas de recarga artículo 111',
        ];
        $endpoints = [];

        foreach ($nombres as $id => $nombre) {
            $endpoints[] = [
                'nombre' => $nombre,
                'clave' => 'e'.$id,
                'url' => $base.'/'.$id,
                'where' => '1=1',
                'campo_id' => 'OBJECTID',
                'bbox' => false,
            ];
        }

        return new DefinicionCapaAbierta(
            codigo: 'CORMACARENA_ECOSISTEMAS',
            disponible: true,
            licencia: 'Ninguna',
            cita: 'Cormacarena. Ecosistemas estratégicos. https://services7.arcgis.com/m7QthefN6swNbXsM/arcgis/rest/services/Ecosistemas_Estrategicos/FeatureServer. Licencia declarada en la ficha del ítem: Ninguna. Acceso: SIG Cormacarena.',
            observacion: 'Servicio público verificado en ArcGIS Online de Cormacarena (Ecosistemas_Estrategicos, nueve capas). La ficha del ítem declara la licencia «Ninguna». La fecha de corte es el mayor dataLastEditDate de las capas.',
            urlServicio: $base,
            layerId: null,
            endpoints: $endpoints,
            itemUrl: 'https://www.arcgis.com/sharing/rest/content/items/61887a5748ed463c85084ab6b03de25d?f=json',
        );
    }
}
