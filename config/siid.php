<?php

return [
    'sheets' => [
        'spreadsheet_id' => env('SIID_GOOGLE_SHEETS_ID', '1Y8j1Qkjq1BfLzFjjUvbhG1JbN10_FmV3HexSTVhLa0Y'),
        'credentials_path' => env('SIID_GOOGLE_CREDENTIALS_PATH'),
        'credentials_json' => env('SIID_GOOGLE_CREDENTIALS_JSON'),
        'main_tabs' => array_values(array_filter(array_map('trim', explode(',', (string) env('SIID_SHEETS_MAIN_TABS', 'Sociodemografico LOOKER,Economicos LOOKER,Ambientales LOOKER'))))),
        'complementary_tabs' => array_values(array_filter(array_map('trim', explode(',', (string) env('SIID_SHEETS_COMPLEMENTARY_TABS', 'INDICE SOCIODEMOGRAFICO,INDICADORES ECONOMICOS,INDICADORES AMB_TERRI_VIVIE,ODS'))))),
        'sync_time' => env('SIID_SYNC_TIME', '02:30'),
        'name_similarity_threshold' => (int) env('SIID_NAME_SIMILARITY_THRESHOLD', 65),
    ],
    'territorial' => [
        'requires_approval' => env('SIID_REQUIERE_APROBACION', true),
    ],
    'looker' => [
        'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('SIID_LOOKER_ALLOWED_HOSTS', 'lookerstudio.google.com,datastudio.google.com'))))),
    ],
];
