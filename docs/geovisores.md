# Geovisores del Meta: arquitectura y ruta de implementación

## Decisión técnica

La solución se divide en cuatro responsabilidades para evitar que una sola herramienta concentre todo el proceso:

1. **PostgreSQL + PostGIS** es la fuente oficial y conserva geometrías, atributos, relaciones, historial y permisos.
2. **QGIS Desktop LTR** es la herramienta de los equipos técnicos para capturar, corregir y analizar información conectándose directamente a PostGIS con credenciales individuales.
3. **GeoServer** publica las capas autorizadas como servicios interoperables WMS y WFS. Para capas muy grandes se incorporará un servicio de teselas vectoriales, sin cambiar la base oficial.
4. **Laravel + Leaflet** controla qué se publica, cómo se agrupa y en qué orden aparece; entrega un visor independiente que puede embeberse en `meta.gov.co` mediante `iframe`.

QGIS no debe actuar como servidor público ni como base de datos. Es el cliente de edición y análisis. Laravel tampoco debe exponer credenciales de PostgreSQL en el navegador.

## Lo implementado en esta entrega

- Catálogo administrativo de capas geográficas.
- Fuentes GeoJSON/WFS y WMS de GeoServer.
- Visores independientes con estados `Borrador`, `Publicado` y `Archivado`.
- Asignación de una misma capa a uno o varios visores.
- Grupos temáticos, rótulo público, orden, opacidad, zoom mínimo/máximo, visibilidad inicial y leyenda por visor.
- Previsualización privada antes de publicar.
- Configuración JSON pública que sólo incluye capas activas de visores publicados.
- Página Leaflet embebible y restringida mediante CSP a los dominios autorizados.
- Registro de auditoría de creación y modificación.
- Semilla inicial con límites municipales del Meta, mantenida como borrador.
- Catálogo de conjuntos SIG y constructor de formularios versionados desde Laravel.
- Contrato autenticado para QGIS en `/api/v1/qgis/datasets` y `/api/v1/qgis/datasets/{slug}/form`.
- Materialización automática de formularios publicados como tablas `capture.*` y vistas filtradas `publication.*`.
- Reglas físicas de obligatoriedad, rangos y listas, aplicadas según la versión en que nació cada campo.
- Bloqueo en PostGIS para impedir que un editor QGIS publique directamente un registro.
- Entorno Docker PostGIS compatible con Apple silicon y copia verificada de SQLite a PostgreSQL.

La administración está en `/admin/geovisores`. El visor público queda en `/visores/{slug}/embed` y su configuración en `/api/public/visores/{slug}/config`.

## Flujo operativo

```text
Técnico en QGIS
      │ edición con usuario individual
      ▼
PostgreSQL/PostGIS ── vistas de publicación ──► GeoServer (WMS/WFS)
      │                                             │
      │ métricas y datos de negocio                 │ servicios cartográficos
      ▼                                             ▼
Laravel (permisos, catálogo, publicación) ──► Leaflet ──► iframe meta.gov.co
```

1. El administrador de datos crea el esquema y otorga permisos mínimos por equipo.
2. Cada técnico configura en QGIS una conexión PostgreSQL con SSL y sin compartir contraseñas.
3. El técnico captura o edita puntos, líneas o polígonos y sus atributos obligatorios.
4. Las tablas operativas pasan por validación; las vistas `publicacion.*` exponen únicamente registros aprobados.
5. GeoServer publica esas vistas. WMS se usa cuando interesa conservar simbología/renderizado del servidor; WFS/GeoJSON se usa para elementos interactivos y popups de volumen moderado.
6. Un administrador registra el servicio en Laravel, lo asigna a un visor, previsualiza y publica.
7. La página oficial consume exclusivamente el `iframe` publicado.

## Modelo recomendado para puntos críticos

La geometría principal debe vivir en una tabla `riesgos.puntos_criticos` con `geometry(Point, 4326)` e índice GiST. La información repetible no debe convertirse en decenas de columnas del punto:

- `riesgos.puntos_criticos`: código, nombre, municipio, ubicación, tipología, nivel, estado, población/cultivos/animales afectados y responsable.
- `riesgos.intervenciones`: punto crítico, fecha, tipo de intervención, horas máquina, maquinaria, costo, observaciones.
- `riesgos.obras`: punto crítico, proyecto, alcance, valor, fuente, avance y estado.
- `riesgos.ayudas_humanitarias`: punto crítico, fecha, tipo, cantidad, hogares/personas beneficiadas y soporte.
- `riesgos.evidencias`: entidad relacionada, archivo, fecha, autor y metadatos.
- `riesgos.historial_estados`: estado anterior/nuevo, fecha, usuario y motivo.

Este diseño permite acumular muchas intervenciones o entregas sobre un mismo punto, calcular indicadores sin duplicar geometrías y conservar trazabilidad.

## Seguridad y gobierno del dato

- Separar roles de base de datos: propietario, migraciones, edición QGIS, publicación GeoServer y lectura analítica.
- Exigir TLS, restringir PostgreSQL por red/VPN y nunca publicarlo directamente a Internet.
- QGIS edita tablas operativas; GeoServer lee vistas de publicación con columnas expresamente permitidas.
- Mantener `GEOVISOR_FRAME_ANCESTORS` limitado a los dominios oficiales.
- Validar geometrías, catálogos, rangos y campos obligatorios antes de aprobar un registro.
- Respaldar PostgreSQL y el directorio externo de datos/configuración de GeoServer.

## Conectar QGIS en macOS

1. Instalar el paquete oficial **QGIS LTR** para Apple silicon o Intel.
2. En Laravel, abrir **Datos SIG**, diseñar el formulario y publicarlo. Sólo una versión publicada puede convertirse en capa.
3. Materializar la versión publicada con `php artisan geodata:materialize {slug}` usando la conexión administrativa de PostgreSQL.
4. En QGIS abrir **Navegador → PostgreSQL → Nueva conexión** y configurar host, puerto, base y el usuario editor. En el entorno local: host `127.0.0.1`, puerto `55432`, base `siid_meta` y usuario `qgis_editor`.
5. Activar SSL en servidores remotos. No guardar una contraseña institucional compartida en proyectos `.qgz`.
6. Abrir únicamente la tabla del esquema `capture`, comprobar SRID 4326, activar edición y crear la geometría. `id`, versión, autor y fechas se completan en base de datos.
7. QGIS puede guardar `draft` o enviar `submitted`; no puede asignar `validated` ni `published`. Las vistas `publication` sólo contienen filas publicadas por el flujo administrativo.

El token creado en **Tokens de acceso → Catálogo para QGIS** permite consultar el contrato de formulario con `Authorization: Bearer {token}`. La contraseña PostgreSQL permite editar la capa y nunca debe enviarse al API ni al navegador.

## Preparar PostGIS sin abandonar SQLite

```bash
cp deploy/postgis/secrets/postgres_owner_password.example deploy/postgis/secrets/postgres_owner_password
# Reemplazar el contenido por una contraseña de arranque larga.
docker compose -f compose.postgis.yml up -d --wait
```

La configuración de aplicación no se copia al `.env`. Un administrador abre **Infraestructura SIG** en `/admin/infraestructura-sig` y completa servidor, puerto, base, SSL y las credenciales. Laravel prueba la conexión, crea los usuarios operativos y guarda el contenido cifrado con `APP_KEY` fuera del directorio público.

Desde la misma pantalla, **Preparar y verificar PostGIS** crea el respaldo de SQLite, ejecuta migraciones, copia las tablas en orden de dependencias, compara conteos y materializa los formularios publicados.

El copiador exige un PostgreSQL migrado y vacío, inserta las tablas en orden de dependencias, ajusta secuencias y compara cada conteo. Si encuentra datos en destino, se detiene sin sobrescribirlos. Caché, sesiones, colas y la tabla de migraciones se regeneran; los datos institucionales sí se conservan.

## Corte final SQLite → PostgreSQL

Cuando la preparación termine correctamente, el botón **Activar PostgreSQL** queda habilitado. La activación escribe el estado en el almacén cifrado y reinicia la sesión; no modifica `.env`. El botón **Volver a SQLite** permite recuperación. El archivo `database/database.sqlite` y su respaldo privado se conservan hasta aprobar formalmente el corte.

## Siguientes incrementos

1. Crear el módulo transaccional de puntos críticos y las tablas relacionadas, con formularios Laravel y edición controlada desde QGIS.
2. Instalar/configurar PostGIS y GeoServer en un ambiente de pruebas; publicar las primeras vistas WMS/WFS.
3. Incorporar teselas vectoriales para capas de gran volumen y pruebas de carga.
4. Construir el dashboard sociodemográfico con filtros cruzados por municipio, tarjetas, pirámide poblacional, distribución urbano/rural y mapa.
5. Agregar flujo formal de revisión/aprobación, control de calidad geométrica y versionado de datos.

El criterio de salida del piloto es: dos usuarios editan concurrentemente sin perder datos; un administrador puede ocultar una capa sin despliegue; un borrador no es público; el `iframe` funciona únicamente en dominios autorizados; y mapa, filtros y métricas responden con tiempos acordados sobre el volumen real.
