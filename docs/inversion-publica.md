# Mapa de Inversión Pública del Meta

## Alcance y universos

El módulo consulta datos abiertos del Departamento Nacional de Planeación mediante la API Socrata de `datos.gov.co`. Cada pantalla exige un universo explícito:

- **Gobernación del Meta:** BPIN cuya entidad responsable tiene código `50` o nombre normalizado `Meta`.
- **Territorio Meta:** BPIN con localización reportada en departamento código `50` o nombre `Meta`.
- **Ecosistema territorial:** unión identificable de los dos anteriores, proyectos nacionales regionalizados y SGR vinculables al Meta. Un proyecto se cuenta una sola vez por BPIN.

Estos universos se guardan como banderas independientes. No se deben sumar entre sí porque se superponen.

## Entidades descentralizadas y periodo 2024–2027

El catálogo interno parte de las 10 entidades publicadas en el [mapa del sitio de la Gobernación del Meta](https://meta.gov.co/mapa-del-sitio): AIM, Casa de la Cultura, EDESA, IDERMETA, Instituto de Cultura, Instituto de Turismo, Instituto Departamental de Tránsito y Transporte, Iracá, Lotería del Meta y Unidad de Licores. Todas aparecen en el panorama aunque todavía no tengan proyectos identificados. El catálogo, sus alias, fuente, visibilidad y orden son administrables.

El DNP suele publicar estas iniciativas con la entidad responsable genérica `Meta` (código `50`). Por eso la entidad descentralizada es una clasificación interna, trazable y revisable, no una sustitución del dato original:

- una coincidencia exacta en entidad ejecutora o responsable se confirma automáticamente con alta confianza;
- una coincidencia en nombre, objetivo, programa o JSON original queda como sugerencia;
- una persona administradora puede confirmar, reasignar o rechazar la sugerencia;
- una asignación confirmada o rechazada manualmente no se sobrescribe en sincronizaciones posteriores;
- cada cambio registra valor anterior, valor nuevo, usuario, fecha, método y evidencia en la auditoría.

Un BPIN admite una entidad principal y varias colaboradoras. Los indicadores por entidad solo cuentan la principal confirmada, de modo que la suma general conserva BPIN únicos.

Las consultas tienen dos modos:

- **Ejecución 2024–2027** (predeterminado): exige al menos una fila financiera dentro del periodo y suma vigente, comprometido, obligado y pagado únicamente entre 2024 y 2027.
- **Horizonte 2024–2027**: incluye proyectos cuyo intervalo se cruza con el periodo, cuenta el valor total una sola vez y muestra aparte la ejecución financiera disponible de 2024–2027.

Cuando la fuente no permite calcular una cifra, la interfaz muestra **Datos no reportados** o **No calculable**; nunca reemplaza la ausencia con cero.

## Fuentes y campos utilizados

| Conjunto | Uso | Clave |
| --- | --- | --- |
| `cf9k-55fw` | Identidad, objetivo, estado, horizonte, sector, responsable, valores del proyecto y beneficiarios | `bpin` |
| `v4ap-cvae` | Ejecución por vigencia, fuente y entidad financiadora | `bpin` + vigencia + fuente + entidad + tipo de recurso |
| `7mxf-bp6x` | Historial de combinaciones de avances físico y financiero reportados | `bpin` + avances + valor vigente |
| `xikz-44ja` | Localización del proyecto y DIVIPOLA | `bpin` + departamento + municipio |
| `iuc2-3r6h` | Beneficiarios por localización | `bpin` + departamento + municipio |
| `8kfp-z3my` | Productos, metas e indicadores | `bpin` + producto + indicador |
| `nf48-7qwf` | Localización de productos | No publica BPIN en la API actual; se registra advertencia y no se fuerza una asociación |
| `u3qu-swda` | Ejecución regionalizada y DIVIPOLA | `bpin` + vigencia + fuente + territorio |
| `uwns-mbwd` | Contratos y URL del proceso | `bpin` + referencia + proveedor + objeto |
| `yt5q-ekus` | Política transversal y dimensión | `bpin` + política + dimensión + vigencia + mes |
| `wc86-9j4a` | Recursos agregados por entidad territorial | No contiene BPIN; se conserva en tabla territorial separada |
| `mzgh-shtp` | Proyectos SGR, ejecutor y avances | `codigobpin` |

El mapa usa los polígonos municipales MGN 2024 del DANE (capa Municipio, departamento DIVIPOLA `50`). Laravel conserva esa geometría en caché durante 30 días y agrega únicamente el conteo de BPIN del universo seleccionado. Si el servicio geográfico no responde, la lista DIVIPOLA de los 29 municipios sigue disponible como respaldo.

Cada fila normalizada conserva `source_dataset_id`, una clave natural SHA-256 y el JSON publicado. Cada ejecución crea `investment_sync_runs` e `investment_source_snapshots` con URL, fecha de consulta, fecha de corte disponible en los metadatos Socrata, filas recibidas/escritas, advertencias y errores.

## Fórmulas

- **Número de proyectos:** `COUNT(DISTINCT BPIN)` dentro del universo y filtros activos.
- **Valor total:** suma de `investment_projects.total_value`; solo una fila por BPIN.
- **Valores por vigencia:** suma de filas financieras de `v4ap-cvae` después de filtrar los BPIN. La ejecución regionalizada se conserva aparte y no se vuelve a sumar en el panorama.
- **Ejecución financiera agregada mostrada:** `SUM(valor pagado) / SUM(valor vigente) × 100`, únicamente cuando ambos valores existen y el denominador es mayor que cero. La interfaz la rotula **Pagado / vigente**, no como una calificación oficial.
- **Avance físico del panorama:** media simple solo de proyectos que reportan avance. No se usa para calcular recursos financieros.
- Un valor vacío permanece `NULL` y se muestra como **No reportado**. No se transforma en cero.

El conjunto consultado no expone un campo inequívoco llamado “recursos aprobados” para todos los tipos de proyecto. Por eso el MVP no rebautiza `valorvigente` como aprobado.

## Alertas internas

Las alertas son reglas de gestión configurables, no criterios oficiales del DNP:

- ejecución pagada/vigente inferior a `INVESTMENT_LOW_EXECUTION_PERCENT`;
- diferencia entre avances físico y financiero mayor o igual a `INVESTMENT_PROGRESS_GAP_POINTS`;
- última sincronización anterior a `INVESTMENT_STALE_AFTER_DAYS`;
- texto de estado asociado a suspensión, cancelación, criticidad o inviabilidad;
- horizonte reportado con finalización dentro de `INVESTMENT_ENDING_WITHIN_DAYS`;
- otro BPIN con el mismo nombre publicado, marcado solo como posible duplicidad;
- ausencia de localización, responsable, información financiera o avance físico.

## Actualización

```bash
# Carga acotada y visible en terminal
/opt/homebrew/opt/php@8.3/bin/php artisan investments:sync --universe=ecosystem --limit=250

# Carga sin límite
/opt/homebrew/opt/php@8.3/bin/php artisan investments:sync --universe=ecosystem --limit=0

# Carga en cola
/opt/homebrew/opt/php@8.3/bin/php artisan investments:sync --universe=ecosystem --queue

# Reprocesar el catálogo de entidades sin descargar nuevamente las fuentes
/opt/homebrew/opt/php@8.3/bin/php artisan investments:classify-entities
```

La aplicación pagina Socrata, reintenta fallos transitorios y actualiza por claves naturales. Una nueva ejecución actualiza registros existentes y no duplica proyectos, vigencias, localizaciones, productos o contratos equivalentes. Un fallo de una fuente relacionada deja la ejecución en `partial`; los datos válidos de las demás fuentes permanecen disponibles.

Opcionalmente configure `SOCRATA_APP_TOKEN` para mejorar los límites de la API. Nunca se envía este token al navegador.

Después de cada sincronización se ejecuta automáticamente la clasificación y se registran las cantidades de proyectos confirmados, sugeridos y sin clasificar. La revisión se encuentra en **Administración → Clasificaciones**. La ficha `/inversion-publica/entidades/{slug}` permite combinar periodo, municipio, sector, estado y texto de búsqueda.

## Verificación real del 8 de septiembre de 2026

Se ejecutó una muestra reproducible con:

```bash
/opt/homebrew/opt/php@8.3/bin/php artisan investments:sync --universe=ecosystem --limit=10
```

Resultado local final: 10 BPIN únicos seleccionados de forma equilibrada entre Gobernación, localización, beneficiarios y SGR; 961 filas recibidas y 949 normalizadas. Incluyó 43 financieras, 13 seguimientos, 618 localizaciones de proyecto, 103 localizaciones de beneficiarios, 18 productos, 107 ejecuciones regionalizadas, 3 contratos, 20 focalizaciones transversales, 4 registros SGR y 10 recursos territoriales. La ejecución terminó en estado `completed`, sin fuentes fallidas. El número elevado de localizaciones confirma por qué los valores del proyecto no pueden sumarse por cada fila territorial.

Como control de cobertura, consultas Socrata independientes devolvieron 3.168 BPIN con entidad responsable Meta y 17.833 BPIN localizados en Meta. Son universos superpuestos y no se suman. La página pública de MapaInversiones presenta tableros con filtros y cortes propios, pero no expuso en su portada una cifra departamental directamente equivalente a estas reglas. Por ello no se declara igualdad: las diferencias esperables provienen del universo, corte, tipo de proyecto, regionalización y definición financiera. La comparación exacta debe hacerse seleccionando en el portal el mismo universo y corte que muestre la última sincronización.

## Integración con reuniones

Desde la ficha de un proyecto se puede:

1. agregar el BPIN a una reunión visible para el usuario, con motivo y preguntas;
2. crear un compromiso ligado al borrador de esa reunión;
3. registrar una decisión ligada al proyecto y versión del acta;
4. navegar del proyecto a la reunión y desde el acta o compromiso de vuelta al proyecto.

Los compromisos nuevos siempre nacen en estado `draft`. Conservan la regla existente: deben clasificarse y el acta requiere aprobación humana antes de volverse oficial.
