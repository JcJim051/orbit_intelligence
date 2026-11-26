# Catálogo y micrositio SIID

Este módulo separa dos orígenes y los publica desde una sola tabla polimórfica:

- **Indicadores SIID**: catálogo sincronizado desde Google Sheets. Las series siguen en Sheets y los tableros en Looker Studio o Superset.
- **Indicadores territoriales**: ficha y series administradas por el flujo territorial existente del SIID.

## Despliegue

El módulo está diseñado para **PostgreSQL/PostGIS**. En producción las migraciones de estructura deben ejecutarse siempre con el rol propietario `managed_postgis_admin`; la aplicación opera después con el rol restringido `managed_postgis`.

```bash
cd /srv/siid-meta/current

sudo -u www-data php artisan migrate:status --database=managed_postgis_admin
sudo -u www-data php artisan migrate --database=managed_postgis_admin --force
sudo -u www-data php artisan db:seed --class=Database\\Seeders\\SiidCatalogSeeder --database=managed_postgis_admin --force

sudo -u www-data php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache

sudo -u www-data php artisan siid:sincronizar-listado --dry-run
sudo -u www-data php artisan siid:sincronizar-listado --apply
sudo -u www-data php artisan siid:migrar-publicaciones --apply

sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

Si `public/storage` ya existe, `storage:link` puede indicar que el enlace ya fue creado; no es un error. Las imágenes editoriales se almacenan en `storage/app/public/micrositio/indicadores` y se sirven mediante ese enlace.

### Verificación PostgreSQL posterior al despliegue

```bash
sudo -u www-data php artisan migrate:status --database=managed_postgis_admin
sudo -u www-data php artisan tinker --execute="dump(DB::connection('managed_postgis')->selectOne(\"select current_database() as database, current_user as role, to_regclass('siid_publicacion.publicaciones') as publicaciones\"));"
```

La verificación debe devolver la base productiva, el rol de aplicación y `siid_publicacion.publicaciones`. SQLite se utiliza únicamente como base efímera de algunas pruebas automatizadas; no interviene en el despliegue ni en la operación del SIID.

El primer diagnóstico puede reportar áreas sin equivalencia. Cree las áreas canónicas y sus equivalencias desde el panel antes de ejecutar `--apply`.

## Google Sheets

1. Cree una cuenta de servicio en Google Cloud con Google Sheets API habilitada.
2. Comparta el listado maestro con el correo `client_email` de esa cuenta como lector.
3. Guarde el JSON fuera del repositorio y configure:

```dotenv
SIID_GOOGLE_SHEETS_ID=1Y8j1Qkjq1BfLzFjjUvbhG1JbN10_FmV3HexSTVhLa0Y
SIID_GOOGLE_CREDENTIALS_PATH=/ruta/segura/siid-google.json
SIID_SHEETS_MAIN_TABS="Sociodemografico LOOKER,Economicos LOOKER,Ambientales LOOKER"
SIID_SHEETS_COMPLEMENTARY_TABS="INDICE SOCIODEMOGRAFICO,INDICADORES ECONOMICOS,INDICADORES AMB_TERRI_VIVIE,ODS"
SIID_SYNC_TIME=02:30
SIID_NAME_SIMILARITY_THRESHOLD=65
```

También se admite `SIID_GOOGLE_CREDENTIALS_JSON`, pero en producción se recomienda la ruta a un secreto montado. El scheduler debe ejecutar `php artisan schedule:run` cada minuto y debe existir un worker para los trabajos manuales en cola.

### Incorporar columnas nuevas

Las pestañas principales admiten, entre otros, estos encabezados editoriales sincronizados:

- `Descripción del indicador`: texto descriptivo que se utiliza cuando el webmaster no registra una descripción pública propia.
- `Enlace de la fuente`: URL oficial que respalda el indicador.
- `Enlace o UUID del tablero`: URL de Looker Studio o UUID embebido de Superset.

Si el webmaster deja vacíos `Enlace de la fuente` o `URL embed o UUID` en la publicación, el micrositio usa los valores sincronizados. Un UUID se interpreta automáticamente como Superset y una URL como Looker cuando no se informa `Tipo tablero`.

1. Agregue el alias en `ListadoMaestroParser::ALIASES`.
2. Agregue la columna sincronizada mediante migración y al mapeo de `SincronizadorListadoSiid::attributes()`.
3. Una columna opcional ausente no debe sobrescribir un valor previo.
4. Añada pruebas para el alias, ausencia de la columna y preservación editorial.

## Superset

Use las variables `SUPERSET_URL`, `SUPERSET_USERNAME`, `SUPERSET_PASSWORD`, `SUPERSET_GUEST_ROLE` y `SUPERSET_EMBED_ALLOWED_DOMAINS`. En Superset habilite el dashboard para embedded y copie su UUID, no la URL interna. Laravel genera el guest token; las credenciales nunca llegan al navegador.

Para indicadores territoriales el usuario PostgreSQL lector recibe únicamente `USAGE` del schema `siid_territorial` y `SELECT` sobre:

- `siid_territorial.v_territorial_serie`
- `siid_territorial.v_territorial_ultimo_valor`

La migración aplica esos permisos al rol lector configurado. Para comprobarlos:

```sql
SET ROLE siid_reader;
SELECT * FROM siid_territorial.v_territorial_ultimo_valor LIMIT 1;
RESET ROLE;
```

## Looker Studio

Solo se aceptan URLs HTTPS con `/embed/` en los hosts configurados por `SIID_LOOKER_ALLOWED_HOSTS`. Los dominios admitidos por defecto son `lookerstudio.google.com` y el dominio histórico `datastudio.google.com`. El middleware agrega esos orígenes y Superset a `Content-Security-Policy: frame-src`.

## Roles

- Administrador, Gerente y Gestor SIID pueden consultar/sincronizar el catálogo según sus facultades.
- Webmaster puede editar exclusivamente publicación, etiqueta, orden, destacado y tablero.
- El equipo autorizado mantiene el CRUD territorial actual.
- Las dependencias continúan reportando valores desde el seguimiento mensual; no reciben acceso de autoría a fichas o series.

## Operación segura

- Ejecute primero `--dry-run` y revise todos los conteos y conflictos.
- Una lectura incompleta de cualquiera de las tres pestañas principales falla sin aplicar cambios.
- Los indicadores ausentes tras una lectura completa se inactivan y ocultan; nunca se eliminan.
- La sincronización no modifica los campos de `siid_publicacion.publicaciones`.
- Los módulos `Indicator` y `MicrositioIndicador` quedan en compatibilidad durante una versión. No deben usarse para crear nuevo catálogo.
