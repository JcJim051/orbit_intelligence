<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco privado para pasivas, evidencias y soportes
    |--------------------------------------------------------------------------
    | El disco "local" de Laravel apunta a storage/app/private y no se publica.
    */
    'disk' => env('REPORTE_SECTORIAL_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Base del techo
    |--------------------------------------------------------------------------
    | Columna de la pasiva que se suma para calcular el techo por proyecto, fuente y dependencia.
    | Valores admitidos: apropiacion_definitiva (por defecto), apropiacion_inicial, cdp, compromisos.
    */
    'base_techo' => env('REPORTE_SECTORIAL_BASE_TECHO', 'apropiacion_definitiva'),

    /*
    |--------------------------------------------------------------------------
    | Columnas esperadas de la pasiva (InfMesPptoCDP del PCT)
    |--------------------------------------------------------------------------
    | Cada campo interno acepta uno o varios encabezados (sin tildes ni mayúsculas importan).
    | La fila de encabezados se detecta buscando el encabezado de "identificacion".
    | Formatos admitidos: .xlsx, .csv (separador , ; o tabulador). El .xls del PCT se guarda antes como .xlsx.
    */
    'pasiva' => [
        'columnas' => [
            'identificacion' => ['IDENTIFICACION PRESUPUESTAL', 'IDENTIFICACION', 'RUBRO PRESUPUESTAL'],
            'concepto' => ['CONCEPTO', 'DESCRIPCION'],
            'apropiacion_inicial' => ['INICIAL', 'APROPIACION INICIAL'],
            'modificaciones' => ['MODIFICACIONES'],
            'contracreditos' => ['CONTRACREDITOS'],
            'creditos' => ['CREDITOS'],
            'reducciones' => ['REDUCCIONES'],
            'adiciones' => ['ADICIONES'],
            'apropiacion_definitiva' => ['DEFINITIVA', 'APROPIACION DEFINITIVA'],
            'cdp' => ['CERTIFICADOS', 'CDP'],
            'compromisos' => ['COMPROMISOS', 'RP', 'REGISTROS'],
            'obligaciones' => ['OBLIGACIONES'],
            'pagos' => ['PAGOS'],
        ],
        'etiquetas' => [
            'identificacion' => 'IDENTIFICACIÓN PRESUPUESTAL',
            'concepto' => 'CONCEPTO',
            'apropiacion_inicial' => 'INICIAL',
            'apropiacion_definitiva' => 'DEFINITIVA',
            'cdp' => 'CERTIFICADOS (Acumulado)',
            'compromisos' => 'COMPROMISOS (Acumulado)',
            'obligaciones' => 'OBLIGACIONES (Acumulado)',
            'pagos' => 'PAGOS (Acumulado)',
        ],
        /*
         * El libro "por periodo" parte certificados, compromisos, obligaciones y pagos
         * en Acumulado y Periodo. Solo Acumulado alimenta la pasiva y el techo.
         * Si falta alguna de estas columnas, la carga se rechaza en lugar de dejar el valor en cero.
         */
        'obligatorias' => ['identificacion', 'concepto', 'apropiacion_inicial', 'apropiacion_definitiva', 'cdp', 'compromisos', 'obligaciones', 'pagos'],
        'acumulado' => ['ACUMULADO'],
        'periodo' => ['PERIODO'],
        'filas_busqueda_encabezado' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tolerancias
    |--------------------------------------------------------------------------
    */
    'tolerancia_focalizacion_porcentaje' => 0.01,
    'tolerancia_techo' => 0.0,

    /*
    |--------------------------------------------------------------------------
    | Evidencias
    |--------------------------------------------------------------------------
    */
    'evidencias' => [
        'max_kb' => 20480,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'xlsx', 'xls', 'docx', 'doc', 'zip', 'csv'],
    ],
];
