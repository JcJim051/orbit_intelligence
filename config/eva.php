<?php

return [
    'dataset_id' => env('EVA_SOCRATA_DATASET_ID', 'uejq-wxrr'),
    'source_url' => 'https://www.datos.gov.co/Agricultura-y-Desarrollo-Rural/Evaluaciones-Agropecuarias-Municipales-EVA-2019-20/uejq-wxrr',
    'department_code' => '50',
    'minimum_year' => 2019,
    'maximum_year' => 2025,
    'default_year' => 2025,
    'default_crop' => 'Maíz',
    'default_metric' => 'production',
    'cache_hours' => (int) env('EVA_MAP_CACHE_HOURS', 6),

    'crops' => [
        'Maíz', 'Arroz', 'Limón', 'Yuca', 'Naranja', 'Mandarina', 'Plátano', 'Cacao',
        'Aguacate', 'Piña', 'Maracuyá', 'Palma de aceite', 'Patilla', 'Soya', 'Caña',
    ],

    'metrics' => [
        'planted_area' => ['label' => 'Área sembrada', 'unit' => 'ha', 'property' => 'area_sembrada'],
        'production' => ['label' => 'Producción', 'unit' => 't', 'property' => 'produccion'],
        'yield' => ['label' => 'Rendimiento', 'unit' => 't/ha', 'property' => 'rendimiento'],
    ],
];
