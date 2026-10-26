# Despliegue de indicadores territoriales

La matriz consolidada está versionada en
`database/seeders/data/Matriz_consolidada_indicadores_PET.xlsx` y se carga de
forma idempotente mediante `IndicadoresTerritorialesSeeder`.

## Producción

Ejecutar desde el usuario con acceso a `sudo`:

```bash
cd /srv/siid-meta/current

git fetch origin main
git pull --ff-only origin main

sudo -u www-data composer install --no-dev --prefer-dist --optimize-autoloader

sudo -u www-data php artisan optimize:clear

sudo -u www-data php artisan migrate \
  --database=managed_postgis_admin \
  --force

sudo -u www-data php artisan db:seed \
  --class='Database\Seeders\IndicadoresTerritorialesSeeder' \
  --database=managed_postgis_admin \
  --force

sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

sudo systemctl reload php8.3-fpm
```

El seeder puede ejecutarse nuevamente: actualiza las fichas que continúan en
precarga y conserva las que ya entraron en revisión, sin duplicar indicadores.

## Verificación

```bash
cd /srv/siid-meta/current

sudo -u www-data php artisan route:list \
  --name=indicadores-territoriales

sudo -u www-data php artisan tinker \
  --execute="dump(App\Models\IndicadorTerritorial::query()->count());"
```

El conteo esperado después de la carga inicial es `153`.

La matriz carga las fichas técnicas. Las series históricas se administran por
indicador desde la vista **Ver**, mediante la plantilla Excel individual y el
flujo de validación del enlace de información.
