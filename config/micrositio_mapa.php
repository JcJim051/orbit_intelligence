<?php

return [
    'base_url' => env('MICROSITIO_MAPA_DATOS_BASE_URL', 'https://www.datos.gov.co'),
    'app_token' => env('SOCRATA_APP_TOKEN'),
    'petroleo' => [
        'dataset_id' => 'fdvb-hsrf',
        'url' => 'https://www.datos.gov.co/Minas-y-Energ-a/Produccion-Fiscalizada-Crudo-Consolidada/fdvb-hsrf',
        'codigo' => 'PETROLEO',
    ],
    'agricultura' => [
        'dataset_id' => 'uejq-wxrr',
        'url' => 'https://www.datos.gov.co/Agricultura-y-Desarrollo-Rural/Evaluaciones-Agropecuarias-Municipales-EVA-2019-20/uejq-wxrr',
        'codigo' => 'AGRO-PROD',
    ],
];
