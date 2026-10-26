<?php

return [

    /*
    | URL pública de la VM de Superset (sin barra final). El navegador la usa
    | para el iframe del embedded SDK; no es un secreto.
    */
    'url' => rtrim((string) env('SUPERSET_URL', ''), '/'),

    'username' => env('SUPERSET_USERNAME'),

    'password' => env('SUPERSET_PASSWORD'),

    'provider' => env('SUPERSET_PROVIDER', 'db'),

    /*
    | Debe coincidir con GUEST_ROLE_NAME en superset_config.py. Superset aplica
    | ese rol en el servidor; el API de guest token no recibe el nombre del rol.
    */
    'guest_role' => env('SUPERSET_GUEST_ROLE', 'Public'),

    /*
    | Conexión de Superset que apunta a la base del SIID. Si queda vacía,
    | el comando de datasets usa la única base registrada.
    */
    'database_id' => env('SUPERSET_DATABASE_ID'),

    'ro_password' => env('SUPERSET_RO_PASSWORD'),

];
