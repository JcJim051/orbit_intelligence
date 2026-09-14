<?php

return [
    'socrata_base_url' => env('SOCRATA_BASE_URL', 'https://www.datos.gov.co'),
    'socrata_app_token' => env('SOCRATA_APP_TOKEN'),
    'page_size' => (int) env('INVESTMENT_SYNC_PAGE_SIZE', 1000),
    'default_limit' => (int) env('INVESTMENT_SYNC_DEFAULT_LIMIT', 250),
    'stale_after_days' => (int) env('INVESTMENT_STALE_AFTER_DAYS', 120),
    'low_execution_percent' => (float) env('INVESTMENT_LOW_EXECUTION_PERCENT', 30),
    'progress_gap_points' => (float) env('INVESTMENT_PROGRESS_GAP_POINTS', 25),
    'ending_within_days' => (int) env('INVESTMENT_ENDING_WITHIN_DAYS', 120),
    'government_period' => [
        'name' => env('INVESTMENT_GOVERNMENT_PERIOD_NAME', 'El Gobierno de la Unidad'),
        'start_year' => (int) env('INVESTMENT_GOVERNMENT_START_YEAR', 2024),
        'end_year' => (int) env('INVESTMENT_GOVERNMENT_END_YEAR', 2027),
    ],
    'meta_boundaries_url' => env(
        'INVESTMENT_META_BOUNDARIES_URL',
        'https://geoportal.dane.gov.co/mparcgis/rest/services/MGN2024/Serv_CapasMGN_2024/FeatureServer/317/query?where=dpto_ccdgo%3D%2750%27&outFields=mpio_cdpmp%2Cmpio_cnmbr&returnGeometry=true&outSR=4326&f=geojson'
    ),

    'datasets' => [
        'basic' => ['id' => 'cf9k-55fw', 'name' => 'Datos básicos de proyectos', 'bpin' => 'bpin'],
        'financial' => ['id' => 'v4ap-cvae', 'name' => 'Ejecución financiera', 'bpin' => 'bpin'],
        'progress' => ['id' => '7mxf-bp6x', 'name' => 'Seguimiento físico y financiero', 'bpin' => 'bpin'],
        'locations' => ['id' => 'xikz-44ja', 'name' => 'Localización de proyectos', 'bpin' => 'bpin'],
        'beneficiaries' => ['id' => 'iuc2-3r6h', 'name' => 'Localización de beneficiarios', 'bpin' => 'bpin'],
        'products' => ['id' => '8kfp-z3my', 'name' => 'Productos, metas e indicadores', 'bpin' => 'bpin'],
        'product_locations' => ['id' => 'nf48-7qwf', 'name' => 'Localización de productos', 'bpin' => null],
        'regionalized' => ['id' => 'u3qu-swda', 'name' => 'Ejecución regionalizada por DIVIPOLA', 'bpin' => 'bpin'],
        'contracts' => ['id' => 'uwns-mbwd', 'name' => 'Contratos asociados', 'bpin' => 'bpin'],
        'policies' => ['id' => 'yt5q-ekus', 'name' => 'Políticas transversales', 'bpin' => 'bpin'],
        'territorial_resources' => ['id' => 'wc86-9j4a', 'name' => 'Recursos distribuidos a entidades territoriales', 'bpin' => null],
        'sgr' => ['id' => 'mzgh-shtp', 'name' => 'Proyectos financiados con SGR', 'bpin' => 'codigobpin'],
    ],

    'municipalities' => [
        '50001' => 'Villavicencio', '50006' => 'Acacías', '50110' => 'Barranca de Upía',
        '50124' => 'Cabuyaro', '50150' => 'Castilla la Nueva', '50223' => 'Cubarral',
        '50226' => 'Cumaral', '50245' => 'El Calvario', '50251' => 'El Castillo',
        '50270' => 'El Dorado', '50287' => 'Fuente de Oro', '50313' => 'Granada',
        '50318' => 'Guamal', '50325' => 'Mapiripán', '50330' => 'Mesetas',
        '50350' => 'La Macarena', '50370' => 'Uribe', '50400' => 'Lejanías',
        '50450' => 'Puerto Concordia', '50568' => 'Puerto Gaitán', '50573' => 'Puerto López',
        '50577' => 'Puerto Lleras', '50590' => 'Puerto Rico', '50606' => 'Restrepo',
        '50680' => 'San Carlos de Guaroa', '50683' => 'San Juan de Arama', '50686' => 'San Juanito',
        '50689' => 'San Martín', '50711' => 'Vista Hermosa',
    ],
];
