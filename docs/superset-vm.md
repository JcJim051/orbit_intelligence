# Superset en una VM de Proxmox

El SIID corre en su propia VM, sin Docker. Superset vive en otra VM de Proxmox y solo lee el esquema `analitica` de PostgreSQL. El navegador nunca recibe la contraseña de la cuenta de servicio: pide un guest token al SIID y el embedded SDK abre el tablero.

## Red

- VM del SIID: aplicación Laravel, PHP, PostgreSQL con el esquema `analitica`.
- VM de Superset: Python, Redis, PostgreSQL de metadatos, Gunicorn y Apache.
- La VM de Superset debe llegar al puerto de PostgreSQL del SIID. No exponga ese puerto a internet; use la red interna de Proxmox.
- El navegador de los visitantes debe llegar a la URL pública de Superset (`SUPERSET_URL`) porque el iframe se carga ahí.

## Paquetes de la VM

Ubuntu 24.04, usuario `superset` sin privilegios de administrador para el proceso.

```bash
sudo apt update
sudo apt install -y python3.11 python3.11-venv python3.11-dev build-essential \
  libssl-dev libffi-dev libsasl2-dev libldap2-dev pkg-config \
  postgresql postgresql-contrib redis-server apache2
sudo useradd --create-home --shell /bin/bash superset
```

## PostgreSQL de metadatos

Es una base distinta a la del SIID. Solo guarda tableros, usuarios y roles de Superset.

```bash
sudo -u postgres psql <<'SQL'
CREATE USER superset_meta WITH PASSWORD 'cambie-esta-clave';
CREATE DATABASE superset_meta OWNER superset_meta;
SQL
```

## Entorno virtual

```bash
sudo -u superset bash <<'BASH'
python3.11 -m venv /home/superset/venv
/home/superset/venv/bin/pip install --upgrade pip setuptools wheel
/home/superset/venv/bin/pip install apache-superset psycopg2-binary gunicorn
BASH
```

Fije la versión de Superset que vaya a operar (`apache-superset==X.Y.Z`) cuando la haya probado. No deje el paquete flotando en producción.

## Configuración

`/home/superset/superset_config.py`:

```python
import os

SECRET_KEY = os.environ["SUPERSET_SECRET_KEY"]
GUEST_TOKEN_JWT_SECRET = os.environ["GUEST_TOKEN_JWT_SECRET"]
GUEST_ROLE_NAME = os.environ.get("GUEST_ROLE_NAME", "Public")

SQLALCHEMY_DATABASE_URI = os.environ["SUPERSET_META_DB"]
BABEL_DEFAULT_LOCALE = "es"
LANGUAGES = {"es": {"flag": "es", "name": "Español"}, "en": {"flag": "us", "name": "English"}}

FEATURE_FLAGS = {
    "EMBEDDED_SUPERSET": True,
    "DASHBOARD_RBAC": True,
}

TALISMAN_ENABLED = True
TALISMAN_CONFIG = {
    "content_security_policy": None,
    "frame_options": None,
}
OVERRIDE_HTTP_HEADERS = {"X-Frame-Options": "ALLOWALL"}
HTML_SANITIZATION = True

# Origen del SIID. Ajuste el dominio real.
SUPERSET_WEBSERVER_DOMAINS = ["superset.meta.gov.co"]
ENABLE_CORS = True
CORS_OPTIONS = {
    "supports_credentials": True,
    "allow_headers": ["*"],
    "resources": ["*"],
    "origins": ["https://siid.meta.gov.co"],
}
WTF_CSRF_ENABLED = True
GUEST_TOKEN_JWT_EXP_SECONDS = 300
SESSION_COOKIE_SAMESITE = "Lax"
SESSION_COOKIE_SECURE = True

CACHE_CONFIG = {
    "CACHE_TYPE": "RedisCache",
    "CACHE_DEFAULT_TIMEOUT": 300,
    "CACHE_KEY_PREFIX": "superset_",
    "CACHE_REDIS_URL": "redis://127.0.0.1:6379/0",
}
DATA_CACHE_CONFIG = CACHE_CONFIG
FILTER_STATE_CACHE_CONFIG = CACHE_CONFIG
EXPLORE_FORM_DATA_CACHE_CONFIG = CACHE_CONFIG
```

Variables de entorno en `/etc/superset/superset.env` (permiso `640`, grupo `superset`):

```bash
SUPERSET_SECRET_KEY=genere-una-clave-larga
GUEST_TOKEN_JWT_SECRET=otra-clave-distinta
GUEST_ROLE_NAME=Public
SUPERSET_META_DB=postgresql+psycopg2://superset_meta:cambie-esta-clave@127.0.0.1:5432/superset_meta
SUPERSET_CONFIG_PATH=/home/superset/superset_config.py
FLASK_APP=superset
```

`GUEST_ROLE_NAME` tiene que ser el mismo valor que `SUPERSET_GUEST_ROLE` en el `.env` del SIID. El API de guest token no recibe el nombre del rol: Superset lo aplica en el servidor.

## Inicializar

```bash
sudo -u superset bash <<'BASH'
set -a; source /etc/superset/superset.env; set +a
cd /home/superset
/home/superset/venv/bin/superset db upgrade
/home/superset/venv/bin/superset fab create-admin \
  --username admin --firstname Admin --lastname Superset \
  --email admin@meta.gov.co --password 'cambie-esta-clave'
/home/superset/venv/bin/superset init
BASH
```

## Rol público de solo lectura

En Superset, Seguridad → Roles, cree o edite `Public` (el nombre de `GUEST_ROLE_NAME`):

- `can read on Dashboard`
- `can read on Chart`
- `can read on Dataset`
- `can read on DashboardFilterStateRestApi`
- `can read on DashboardPermalinkRestApi`
- `can time range on Api`
- `can read on EmbeddedDashboard`

No otorgue SQL Lab, escritura de datasets ni acceso a bases de datos. En cada tablero que se vaya a embeber, marque “Embedded” y copie el UUID. Ese UUID se pega en el indicador del CMS del SIID.

## Usuario de solo lectura sobre `analitica`

En el PostgreSQL del SIID, no en el de metadatos:

```sql
CREATE USER superset_lectura WITH PASSWORD 'cambie-esta-clave';
GRANT USAGE ON SCHEMA analitica TO superset_lectura;
GRANT SELECT ON ALL TABLES IN SCHEMA analitica TO superset_lectura;
ALTER DEFAULT PRIVILEGES IN SCHEMA analitica GRANT SELECT ON TABLES TO superset_lectura;
```

Las vistas que debe conectar Superset son:

- `analitica.v_pasiva_por_grupo_fuente`
- `analitica.v_pasiva_por_sector`
- `analitica.v_pasiva_fuente_sector`

La tabla `analitica.pasiva_rubro` también es de lectura. El usuario no necesita `INSERT` ni `UPDATE`: la carga la hace el SIID (`php artisan analitica:importar-pasiva` o la pantalla Cargar pasiva).

En Superset, Datos → Bases de datos, agregue:

```text
postgresql+psycopg2://superset_lectura:cambie-esta-clave@IP-INTERNA-DEL-SIID:5432/NOMBRE_BD
```

Desactive la edición de datos y SQL Lab para esa conexión. Los datasets del tablero público deben usar las vistas. La columna `sector` guarda el texto `código — nombre`. El SIID arma el RLS del usuario autenticado con la etiqueta de su dependencia (`codigo — nombre`) y, si el código es simple, con `sector LIKE 'codigo — %'`.

## Gunicorn

`/etc/systemd/system/superset.service`:

```ini
[Unit]
Description=Superset
After=network.target postgresql.service redis-server.service

[Service]
User=superset
Group=superset
EnvironmentFile=/etc/superset/superset.env
WorkingDirectory=/home/superset
ExecStart=/home/superset/venv/bin/gunicorn \
  --bind 127.0.0.1:8088 \
  --workers 4 \
  --timeout 120 \
  --limit-request-line 0 \
  "superset.app:create_app()"
Restart=on-failure

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now superset
```

## Apache como proxy

Habilite `proxy`, `proxy_http`, `headers` y `ssl`. Un virtual host mínimo:

```apache
<VirtualHost *:443>
    ServerName superset.meta.gov.co
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/superset.crt
    SSLCertificateKeyFile /etc/ssl/private/superset.key

    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "https"
    ProxyPass / http://127.0.0.1:8088/
    ProxyPassReverse / http://127.0.0.1:8088/

    Header always unset X-Frame-Options
    Header always set Content-Security-Policy "frame-ancestors 'self' https://siid.meta.gov.co"
</VirtualHost>
```

Sin `frame-ancestors` el navegador bloquea el iframe del micrositio. El origen tiene que ser el dominio real del SIID, el mismo que está en `CORS_OPTIONS`.

## Variables del SIID

En el `.env` de la VM del SIID:

```bash
SUPERSET_URL=https://superset.meta.gov.co
SUPERSET_USERNAME=cuenta_servicio
SUPERSET_PASSWORD=clave-de-la-cuenta
SUPERSET_PROVIDER=db
SUPERSET_GUEST_ROLE=Public
```

La cuenta de servicio solo inicia sesión contra `/api/v1/security/login` para pedir guest tokens. No la use como usuario humano del CMS. El token público sale únicamente para indicadores publicados y marcados como públicos; cualquier otro caso responde 403. El token de un usuario del SIID lleva RLS de su sector o dependencia.

## Comprobar

1. `systemctl status superset` y `curl -I http://127.0.0.1:8088/health`.
2. Entre a Superset, idioma español, y cree el tablero con una vista de `analitica`.
3. Copie el UUID embebido en el indicador del CMS y publique la sección.
4. Abra `/micrositio/indicadores` sin sesión. El tablero debe cargar y el CSV del indicador FP-01 debe salir de `analitica`, no de datos escritos a mano.
5. Entre al SIID con un usuario de un solo sector y abra Tableros (Superset). La cláusula RLS debe nombrar `código — nombre` de su dependencia.
