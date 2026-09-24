# Capa EVA UPRA (Meta)

La capa `eva-agricola-meta` muestra las Evaluaciones Agropecuarias Municipales como coropleta de los municipios del Meta. Se crea en estado **Borrador** mediante `GeoPublicationSeeder` y no reemplaza las capas de aptitud agrícola de la UPRA.

## Fuente y unión espacial

- Fuente: [Evaluaciones Agropecuarias Municipales – EVA 2019–2025](https://www.datos.gov.co/Agricultura-y-Desarrollo-Rural/Evaluaciones-Agropecuarias-Municipales-EVA-2019-20/uejq-wxrr).
- ID Socrata: `uejq-wxrr`. No usar el conjunto legado `2pnw-mmge`.
- Filtro territorial: departamento DANE `50` (Meta).
- Unión: `c_digo_dane_municipio` de EVA con `mpio_cdpmp` de los límites MGN 2024 del DANE, normalizados como texto de cinco dígitos.

## Endpoint y cálculo

`GET /api/public/geodata/eva-agricola-meta?year=2025&crop=Ma%C3%ADz&metric=production`

Los filtros admitidos son año, cultivo y métrica (`planted_area`, `production` o `yield`). Socrata suma área sembrada, área cosechada y producción por municipio antes de transferir las filas. El rendimiento se calcula como `producción agregada / área cosechada agregada`; nunca se promedian los rendimientos de las filas.

La respuesta se conserva en caché durante seis horas y se une a los polígonos municipales oficiales. Los municipios sin reporte permanecen en el mapa con valor nulo y color de "Sin reporte". Si la geometría oficial no está disponible, el endpoint responde `503` y el visor muestra un error explícito.
