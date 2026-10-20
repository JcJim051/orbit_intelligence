<?php

return [

    /*
    | Tiempos en segundos. La página pequeña evita cargar un GeoJSON entero.
    | reintentos es el máximo de reintentos (además del primer intento).
    */

    'http' => [
        'connect_timeout' => (int) env('INTELIGENCIA_CONNECT_TIMEOUT', 30),
        'timeout' => (int) env('INTELIGENCIA_HTTP_TIMEOUT', 180),
        'reintentos' => (int) env('INTELIGENCIA_REINTENTOS', 2),
        'reintento_ms' => (int) env('INTELIGENCIA_REINTENTO_MS', 1000),
        'paquete_timeout' => (int) env('INTELIGENCIA_PAQUETE_TIMEOUT', 1800),
        'capa_timeout' => (int) env('INTELIGENCIA_CAPA_TIMEOUT', 3600),
        'tamano_pagina' => (int) env('INTELIGENCIA_TAMANO_PAGINA', 50),
    ],

    'municipios_dane_url' => env(
        'INTELIGENCIA_MUNICIPIOS_URL',
        'https://geoportal.dane.gov.co/mparcgis/rest/services/MGN2025/Serv_CapasMGN_2025/FeatureServer/317',
    ),

    /*
    | Clases de suelo que cuentan como restricción. La comparación es por texto,
    | sin distinguir mayúsculas. Suburbano y expansión no entran.
    */
    'restricciones_suelo' => [
        'proteccion',
        'protección',
        'pomca',
        'conservacion',
        'conservación',
    ],

    /*
    | Una captura de OpenStreetMap, encuadrada al polígono y guardada en el análisis.
    | Esri World Imagery no se descarga: sus términos no permiten almacenar las teselas.
    */
    /*
    | El PNG se compone con teselas al zoom del encuadre, sin ampliar teselas de un zoom menor.
    | ancho_css es el ancho útil de una A4 con el margen de 1,2 cm de Dompdf, en px a 96 dpi.
    | escala es la resolución del PNG respecto de ese ancho: Dompdf incrusta el PNG completo.
    */
    /*
    | Binario de GDAL para el shapefile. PHP-FPM no hereda el PATH de Homebrew.
    | Si queda vacío se busca en /opt/homebrew/bin, /usr/local/bin, /usr/bin y el PATH.
    */
    'ogr2ogr' => env('OGR2OGR_PATH'),

    'mapa' => [
        'osm_plantilla' => env('INTELIGENCIA_OSM_TILES', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'agente' => 'SIID-inteligencia/1.0',
        'margen' => 0.2,
        'zoom_max' => 19,
        'ancho_css' => 700,
        'escala' => 2,
        'ancho_max' => 1680,
        'alto_max' => 1680,
        'teselas_max' => 64,
    ],

];
