# ActaLab

Aplicación Laravel para recibir grabaciones desde varios iPhone, transcribirlas, preparar un borrador de acta, exigir revisión humana, publicar copias independientes en Google Drive y consultar proyectos de inversión pública del Meta vinculados a reuniones y compromisos.

## Arquitectura del home lab sin Docker

- XAMPP aporta Apache.
- PHP 8.3 de Homebrew ejecuta Laravel mediante PHP-FPM.
- PostgreSQL 16 conserva los datos.
- Redis atiende colas y caché.
- FFmpeg y FFprobe validan y fragmentan los audios.
- Dos procesos `launchd` mantienen activos el worker y el scheduler.

No se usa Docker. El PHP incluido en el XAMPP instalado actualmente es anterior a PHP 8.3 y no puede ejecutar Laravel 13. Por eso Apache delega los archivos PHP a Homebrew PHP-FPM en `127.0.0.1:9000`; no se debe apuntar este proyecto al `php` de XAMPP.

## Requisitos en macOS

El equipo ya dispone de PHP 8.3 y PostgreSQL 16. Instale los componentes faltantes y arranque los servicios:

```bash
brew install redis ffmpeg
brew services start php@8.3
brew services start postgresql@16
brew services start redis
```

Verifique las versiones:

```bash
/opt/homebrew/opt/php@8.3/bin/php -v
/opt/homebrew/opt/postgresql@16/bin/pg_isready
/opt/homebrew/bin/redis-cli ping
/opt/homebrew/bin/ffprobe -version
```

PHP debe ser 8.3 o superior, PostgreSQL debe responder y Redis debe devolver `PONG`.

## Base de datos

Cree el usuario y la base. El primer comando solicitará una contraseña:

```bash
/opt/homebrew/opt/postgresql@16/bin/createuser --pwprompt actalab
/opt/homebrew/opt/postgresql@16/bin/createdb --owner=actalab actalab
```

## Instalar la aplicación

Apache de XAMPP no puede atravesar por defecto la carpeta protegida `~/Documents`. Para el despliegue, copie el proyecto a `htdocs/actalab`; conserve este repositorio como fuente de desarrollo.

```bash
cp -R /Users/jonathanjimenez/Documents/GIEE/aplicacion /Applications/XAMPP/xamppfiles/htdocs/actalab
cd /Applications/XAMPP/xamppfiles/htdocs/actalab
cp .env.example .env
/opt/homebrew/opt/php@8.3/bin/php /opt/homebrew/bin/composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Configure `.env` como mínimo:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://actalab.local
APP_DOMAIN=actalab.local
TRUSTED_PROXIES=127.0.0.1

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=actalab
DB_USERNAME=actalab
DB_PASSWORD=su-clave-postgresql

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis
CACHE_STORE=redis

MEETING_AI_DRIVER=fake
```

Complete la instalación:

```bash
/opt/homebrew/opt/php@8.3/bin/php artisan key:generate
/opt/homebrew/opt/php@8.3/bin/php artisan migrate --force
/opt/homebrew/opt/php@8.3/bin/php artisan storage:link
/opt/homebrew/opt/php@8.3/bin/php artisan users:create admin@example.com --name="Administrador" --role=admin
sudo chgrp -R _www storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

Para una demostración sin llamadas externas use `MEETING_AI_DRIVER=fake`. Para procesar audio real, cambie a `openai` y configure `OPENAI_API_KEY`.

## Configurar PHP 8.3 y XAMPP

Copie los límites de carga para que PHP acepte el máximo de 500 MB del MVP:

```bash
sudo cp deploy/xampp/php-upload-limits.ini /opt/homebrew/etc/php/8.3/conf.d/99-actalab.ini
brew services restart php@8.3
```

Para acceso local, instale el VirtualHost incluido:

```bash
sudo cp deploy/xampp/actalab-local.conf /Applications/XAMPP/xamppfiles/etc/extra/actalab.conf
```

Agregue esta línea al final de `/Applications/XAMPP/xamppfiles/etc/httpd.conf`:

```apache
Include etc/extra/actalab.conf
```

Agregue `127.0.0.1 actalab.local` a `/etc/hosts`, valide Apache y arránquelo:

```bash
sudo /Applications/XAMPP/xamppfiles/bin/apachectl -t
sudo /Applications/XAMPP/xamppfiles/xampp startapache
```

Abra `http://actalab.local`. Si aparece un error 503, compruebe que `php@8.3` esté activo y escuchando en el puerto 9000.

### Dominio público y HTTPS

No exponga `actalab.local` a Internet. Sustituya el VirtualHost local por `deploy/xampp/actalab-production.conf`, cambie el dominio y las rutas del certificado, y configure:

```dotenv
APP_URL=https://actas.su-dominio.com
APP_DOMAIN=actas.su-dominio.com
SESSION_SECURE_COOKIE=true
GOOGLE_DRIVE_REDIRECT_URI=https://actas.su-dominio.com/oauth/google-drive/callback
```

El router debe enviar 80/443 al home lab. Como alternativa, un túnel HTTPS local puede terminar TLS y reenviar a Apache; en ese caso limite `TRUSTED_PROXIES` a las direcciones reales del túnel. Nunca use `TRUSTED_PROXIES=*` en un host público.

## Worker y scheduler persistentes

Pruebe primero ambos procesos en terminales separadas:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/actalab
/opt/homebrew/opt/php@8.3/bin/php artisan queue:work redis --queue=audio,analysis,exports,investments,default --tries=3 --timeout=7200
/opt/homebrew/opt/php@8.3/bin/php artisan schedule:work
```

Para que reinicien con la sesión de macOS, instale los LaunchAgents incluidos:

```bash
cp deploy/launchd/com.actalab.queue.plist ~/Library/LaunchAgents/
cp deploy/launchd/com.actalab.scheduler.plist ~/Library/LaunchAgents/
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.actalab.queue.plist
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/com.actalab.scheduler.plist
```

Después de desplegar código nuevo ejecute `/opt/homebrew/opt/php@8.3/bin/php artisan queue:restart`. Los registros quedan en `storage/logs`.

## Google Drive redundante

1. Habilite Google Drive API en Google Cloud.
2. Configure la pantalla de consentimiento OAuth.
3. Cree un cliente OAuth Web y registre exactamente `GOOGLE_DRIVE_REDIRECT_URI`.
4. Configure `GOOGLE_DRIVE_CLIENT_ID` y `GOOGLE_DRIVE_CLIENT_SECRET` solo en `.env`.
5. Ingrese como administrador, abra **Google Drive** y cree los destinos `Gerencia` y `Personal`.
6. Conecte cada destino iniciando sesión con su cuenta respectiva.

La aplicación solicita `drive.file`, crea su propia carpeta `Actas de reuniones` y cifra los refresh tokens con `APP_KEY`. No cambie `APP_KEY` sin rotar antes los secretos almacenados.

Al aprobar, se crean publicaciones independientes por destino y artefacto. Un fallo en Gerencia no bloquea la copia Personal y queda disponible para reintento.

## Token y Atajo de iOS

Cada usuario abre **iPhone** en el panel, crea un token con el nombre del dispositivo y lo copia una sola vez. Nunca se debe reutilizar un token entre teléfonos.

En Atajos de iOS cree un Atajo que acepte archivos desde la hoja **Compartir**:

1. `Recibir archivos` desde Compartir.
2. `Solicitar entrada` para título y tipo de reunión.
3. `Fecha actual` o fecha solicitada, en ISO 8601.
4. `Obtener UUID` para la clave de idempotencia.
5. `Obtener contenido de URL` con método `POST`, cuerpo `Formulario` y:
   - URL `https://su-dominio/api/v1/meetings`;
   - `audio`: archivo recibido;
   - `title`, `held_at`, `meeting_type`;
   - `recording_consent_confirmed`: `1`;
   - `artifacts`: JSON `["transcript","minutes_markdown","minutes_pdf"]`;
   - cabecera `Authorization`: `Bearer TOKEN_DEL_IPHONE`;
   - cabecera `Idempotency-Key`: UUID generado.
6. Muestre el campo `data.id` de la respuesta.

Los participantes pueden enviarse como JSON:

```json
[{"name":"Ana Pérez","email":"ana@example.com"},{"name":"Luis Gómez"}]
```

Ejemplo:

```bash
curl -X POST https://actas.example.com/api/v1/meetings \
  -H 'Authorization: Bearer TOKEN' \
  -H 'Idempotency-Key: 57A36E1D-5B50-4B34-8FB6-1AFD3BC72C62' \
  -F 'audio=@reunion.m4a' \
  -F 'title=Comité semanal' \
  -F 'held_at=2026-09-01T09:00:00-05:00' \
  -F 'meeting_type=Comité' \
  -F 'recording_consent_confirmed=1' \
  -F 'artifacts=["transcript","minutes_markdown","minutes_pdf"]'
```

## Retención

`AUDIO_RETENTION_DAYS` vacío conserva indefinidamente el original. Un número positivo elimina audios de reuniones aprobadas que superen ese plazo:

```bash
/opt/homebrew/opt/php@8.3/bin/php artisan meetings:prune-audio --dry-run
```

Los fragmentos temporales se eliminan al terminar la transcripción. Las claves de API nunca se entregan al navegador ni al iPhone.

## Desarrollo y pruebas

Desde el repositorio fuente:

```bash
/opt/homebrew/opt/php@8.3/bin/php /opt/homebrew/bin/composer install
npm ci
npm run build
/opt/homebrew/opt/php@8.3/bin/php artisan test
```

Las pruebas usan SQLite en memoria, almacenamiento falso, colas simuladas y proveedores de IA falsos. PostgreSQL es la base soportada para el home lab.

## Mapa de Inversión Pública del Meta

El módulo **Inversión pública** consulta las API oficiales de datos.gov.co mediante Socrata, separa los universos Gobernación, Territorio y Ecosistema y enlaza cada proyecto por BPIN con reuniones, decisiones y compromisos. La guía de fuentes, fórmulas y supuestos está en [docs/inversion-publica.md](docs/inversion-publica.md).

Para una primera carga acotada:

```bash
/opt/homebrew/opt/php@8.3/bin/php artisan investments:sync --universe=ecosystem --limit=250
```

Use `--limit=0` para no imponer límite. Para ejecutar en segundo plano use `--queue`; el worker debe escuchar la cola `investments`. El scheduler encola una actualización semanal usando `INVESTMENT_SYNC_DEFAULT_LIMIT`.
