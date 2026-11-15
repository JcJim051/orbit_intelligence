# Mapa del inicio — Fase 1 (datos quemados)

## Archivos
- `datos-mapa-inicio.json` — 4 capas × 29 municipios del Meta (estructura para Laravel: `capas[].{codigo,nombre,unidad,periodo,fuente,fuente_url,polaridad,decimales,valor_departamental,valores[].{divipola,municipio,valor}}`).
- `datos-mapa-inicio.csv` — misma información para revisión (UTF-8 con BOM, abre bien en Excel).
- `meta-municipios.geojson` — límites municipales DANE MGN 2025, simplificados (≈145 KB), propiedades `divipola`, `municipio`.
- `colombia-departamentos-mini.geojson` — contorno de Colombia por departamentos (≈8 KB), `meta=true` para el Meta (mapa de contexto).
- `mockup/index.html` — inicio nuevo (D3 local, sin servidor). Estados por hash: `#capa=IPM-2018&mun=50350`.
- `mockup/webmaster.html` — pantalla «Mapa del inicio» con vista previa en vivo (pasa la configuración por hash al inicio).
- `build_data.py` — regenera JSON/CSV/`mockup/data.js` desde los archivos oficiales en `raw/`.

## Fuentes
| Capa | Fuente | Periodo | Polaridad |
|---|---|---|---|
| POB-2026 — Población total proyectada | DANE, proyecciones municipales por área 2018-2042 (post COVID-19, act. 30/07/2025) | 2026 | descriptiva |
| NBI-2018 — Personas con NBI | DANE, CNPV 2018 (CNPV-2018-NBI.xlsx, hoja Municipios / Departamento) | 2018 | alto_malo |
| IPM-2018 — IPM municipal | DANE, medida de pobreza multidimensional municipal de fuente censal 2018 | 2018 | alto_malo |
| EDU-CN-2024 — Cobertura neta | MinEducación, datos.gov.co nudc-7mev (municipio) y ji8i-4anb (departamento) | 2024 | alto_bueno |

## Notas para Codex (Laravel)
- Leer el JSON desde `storage/app/micrositio/datos-mapa-inicio.json` (o un seeder). Cuando los indicadores estén en BD, mantener la misma forma.
- Configuración del webmaster (tabla `micrositio_config` o similar): `mostrar_bloque`, `capa_principal`, `capas_adicionales[≤3]`, `periodo (ultimo|fijo, anio)`, `paleta (auto|azul|verde|semaforo)`, `polaridad (auto|alto_bueno|alto_malo)`, `texto_tarjeta`, `texto_cta`, `url_cta`.
- Clases: cuantiles (5) redondeados. Semáforo se invierte cuando la polaridad es `alto_malo`.
- Ranking «n de 29»: 1 = mejor situación (o mayor población en POB).
