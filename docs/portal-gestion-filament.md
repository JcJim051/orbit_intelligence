# Portal interno modular SIID 2.0

## Alcance de la primera entrega

El panel Filament se publica en `/gestion` y reutiliza la autenticación, los usuarios, los roles y las sesiones existentes. No incorpora otro inicio de sesión ni modifica los contratos de QGIS, las APIs públicas, los geovisores embebidos o el portal ciudadano.

Las operaciones de actas, cargas QGIS, catálogo espacial, Datos Abiertos, infraestructura PostGIS, dashboards, fuentes tabulares, inversión pública, usuarios, Drive y dispositivos se ejecutan dentro del marco de Filament. Los formularios especializados conservan sus controladores y endpoints existentes, pero ya no cambian al menú administrativo anterior. Las previsualizaciones públicas o embebibles continúan abriéndose aparte cuando corresponde.

Los submenús se muestran contraídos por defecto y recuerdan la preferencia del usuario. Seguimiento a metas se muestra como “Próximamente” solamente a Gerencia, apoyo de Gerencia y administración técnica.

## Matriz de navegación

| Rol | Actas | Inversión | SIG | Dashboards | Metas | Plataforma |
| --- | --- | --- | --- | --- | --- | --- |
| Usuario | Sí | Sí | No | No | No | No |
| Revisor | Sí | Sí | No | No | No | No |
| Gestor SIID | Sí | Sí | Sí, recursos propios o compartidos | Sí | No | No |
| Apoyo de Gerencia | Sí | Sí | Sí | No | Sí | No |
| Gerente | Sí | Sí | Sí | Sí | Sí | Sí |
| Administrador técnico | Sí | Sí | Sí | Sí | Sí | Sí |

La autorización de registros SIG se concentra en Policies. Las cargas QGIS y la infraestructura PostGIS permanecen restringidas a administración técnica.

## Despliegue atómico en `/srv/siid-meta`

Estos comandos crean un respaldo lógico, preparan un release nuevo a partir del release activo, instalan Filament y los recursos frontend, verifican la aplicación antes de cambiar el enlace `current` y conservan la ruta anterior para reversión.

```bash
set -euo pipefail

cd /srv/siid-meta/repository
git pull --ff-only origin main
git log -1 --oneline

MOMENTO="$(date +%Y%m%d-%H%M%S)"
RELEASE="/srv/siid-meta/releases/gestion-filament-${MOMENTO}"
RESPALDO="/var/backups/siid-meta/siid_meta-pre-filament-${MOMENTO}.dump"
ANTERIOR="$(readlink -f /srv/siid-meta/current)"

sudo install -d -o postgres -g postgres -m 0700 /var/backups/siid-meta
sudo -u postgres pg_dump -Fc -d siid_meta -f "${RESPALDO}"
sudo -u postgres pg_restore --list "${RESPALDO}" >/dev/null

sudo install -d -o www-data -g www-data -m 0755 "${RELEASE}"
sudo rsync -a "${ANTERIOR}/" "${RELEASE}/"
sudo rsync -a --delete \
  --exclude=.git \
  --exclude=.env \
  --exclude=storage \
  --exclude=vendor \
  --exclude=node_modules \
  --exclude=public/build \
  /srv/siid-meta/repository/ "${RELEASE}/"
sudo chown -R www-data:www-data "${RELEASE}"

cd "${RELEASE}"
sudo -u www-data composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
sudo -u www-data env NPM_CONFIG_CACHE="/tmp/siid-npm-${MOMENTO}" npm ci --no-audit --no-fund
sudo -u www-data npm run build
sudo -u www-data php artisan filament:assets

sudo -u www-data php artisan migrate:status --database=managed_postgis_admin
sudo -u www-data php artisan migrate --database=managed_postgis_admin --force
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan optimize
sudo -u www-data php artisan route:list --path=gestion

sudo ln -sfn "${RELEASE}" /srv/siid-meta/current.next
sudo mv -Tf /srv/siid-meta/current.next /srv/siid-meta/current

cd /srv/siid-meta/current
sudo -u www-data php artisan queue:restart
sudo systemctl reload php8.3-fpm

curl -fsS https://siid.testiapp.com/up
curl -sSI https://siid.testiapp.com/gestion | head
curl -fsS https://siid.testiapp.com/demostracion/geovisor >/dev/null
curl -fsS https://siid.testiapp.com/api/public/geodata/limites-municipales-meta >/dev/null

echo "Release anterior: ${ANTERIOR}"
echo "Release activo:   ${RELEASE}"
echo "Respaldo lógico:  ${RESPALDO}"
```

Después del despliegue se debe comprobar manualmente `/gestion` con Administrador técnico y Gestor SIID, además de una previsualización de geovisor. El `302` de `/gestion` sin sesión es correcto: debe apuntar al login existente.

## Reversión de aplicación

La entrega no contiene migraciones destructivas. Si la validación falla, se restaura únicamente el enlace al release anterior; no se restaura la base de datos.

```bash
set -euo pipefail

ANTERIOR="/srv/siid-meta/releases/REEMPLAZAR_CON_EL_RELEASE_ANTERIOR"
test -d "${ANTERIOR}"

sudo ln -sfn "${ANTERIOR}" /srv/siid-meta/current.rollback
sudo mv -Tf /srv/siid-meta/current.rollback /srv/siid-meta/current

cd /srv/siid-meta/current
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan optimize
sudo -u www-data php artisan queue:restart
sudo systemctl reload php8.3-fpm
curl -fsS https://siid.testiapp.com/up
```
