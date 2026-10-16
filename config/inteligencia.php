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

];
