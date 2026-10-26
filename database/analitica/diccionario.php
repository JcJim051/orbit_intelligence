<?php

/**
 * Diccionario versionado del esquema analitica.
 * Un agente puede leer este archivo para proponer vistas o tableros.
 * Publicar una vista nueva sigue siendo una migración revisada por una persona.
 *
 * Las sentencias usan {{nombre}} para la tabla o vista del esquema
 * (analitica.nombre en PostgreSQL, analitica_nombre en SQLite).
 */
$hacienda = 'Secretaría de Hacienda — informe mensual de ejecución presupuestal de gastos';

$col = fn (string $nombre, string $descripcion, string $fuente): array => [
    'nombre' => $nombre,
    'descripcion' => $descripcion,
    'fuente' => $fuente,
];

$montos = [
    $col('definitiva', 'Apropiación definitiva', $hacienda),
    $col('certificado', 'Certificado de disponibilidad (CDP)', $hacienda),
    $col('comprometido', 'Compromisos', $hacienda),
    $col('obligado', 'Obligaciones', $hacienda),
    $col('pagado', 'Pagos acumulados de la línea. No es un movimiento individual de pago.', $hacienda),
];

$corteVigente = [
    $col('fecha_corte', 'Fecha de corte de la pasiva que el tablero está mostrando', $hacienda),
    $col('fecha_carga', 'Momento en que el SIID sincronizó ese archivo a analitica', 'SIID, sincronización de la pasiva'),
    $col('origen', 'Fuente del archivo, con el nombre original', $hacienda),
];

return [
    'pendientes' => [
        [
            'nombre' => 'Puntos críticos viales',
            'descripcion' => 'No hay tabla de puntos críticos viales en el SIID. No se creó una vista ni se inventaron filas.',
        ],
        [
            'nombre' => 'Horas máquina',
            'descripcion' => 'No hay tabla de horas máquina. No se creó una vista ni se inventaron filas.',
        ],
        [
            'nombre' => 'Pagos de pasiva por BPIN',
            'descripcion' => 'No hay un libro de pagos por BPIN. pasiva_lineas.pagos es el acumulado de la línea presupuestal. Si esa línea trae BPIN, v_pasiva_lineas_vigentes lo muestra, pero no existe el movimiento de cada pago.',
        ],
        [
            'nombre' => 'Geometrías de los conjuntos espaciales',
            'descripcion' => 'Cada dataset materializa su propia tabla PostGIS. v_geodatos_conjuntos solo lista el catálogo (nombre, sector, tipo y estado), sin las geometrías.',
        ],
    ],

    'vistas' => [
        [
            'nombre' => 'v_pasiva_vigente',
            'dominio' => 'Pasiva',
            'descripcion' => 'Rubros de la pasiva del corte más reciente. Si ese corte tiene más de un archivo, queda el de fecha de carga más nueva. Los cortes anteriores siguen en analitica.pasiva_rubro. Las columnas de esta vista no se reordenan: una columna nueva solo se agrega al final, para no romper el dataset de Superset.',
            'fuente' => $hacienda,
            'corte' => 'Máximo corte cargado. Dentro de ese corte, el sha256 con cargado_en más reciente.',
            'columnas' => [
                ...$corteVigente,
                $col('unidad', 'Unidad ejecutora del PCT', $hacienda),
                $col('rubro', 'Rubro presupuestal', $hacienda),
                $col('concepto', 'Descripción del rubro', $hacienda),
                $col('fuente_codigo', 'Código de la fuente en el catálogo PCT', $hacienda),
                $col('fuente', 'Fuente como código — nombre. Si no está en el catálogo: código — Sin catálogo', 'Catálogo de fuentes de financiación'),
                $col('grupo_fuente', 'SGP, SGR, Recursos propios, Crédito, Otras de la Nación o Sin catálogo', 'Catálogo de fuentes de financiación'),
                $col('sector', 'Sector de inversión como código — nombre. En funcionamiento no hay sector de inversión.', $hacienda),
                $col('bpin', 'BPIN del proyecto de inversión, o Sin BPIN / No aplica', $hacienda),
                $col('es_inversion', 'Inversión o Funcionamiento / deuda', $hacienda),
                ...$montos,
            ],
            'sql' => <<<'SQL'
SELECT
    r.corte AS fecha_corte,
    r.cargado_en AS fecha_carga,
    r.origen,
    r.unidad,
    r.rubro,
    r.concepto,
    r.fuente_codigo,
    r.fuente,
    r.grupo_fuente,
    r.sector,
    r.bpin,
    r.es_inversion,
    r.definitiva,
    r.certificado,
    r.comprometido,
    r.obligado,
    r.pagado
FROM {{pasiva_rubro}} r
INNER JOIN (
    SELECT corte, sha256
    FROM {{pasiva_rubro}}
    WHERE corte = (SELECT MAX(corte) FROM {{pasiva_rubro}})
    ORDER BY cargado_en DESC, id DESC
    LIMIT 1
) vigente ON r.corte = vigente.corte AND r.sha256 = vigente.sha256
SQL,
        ],
        [
            'nombre' => 'v_pasiva_corte_vigente',
            'dominio' => 'Pasiva',
            'descripcion' => 'Una sola fila con el corte que está viendo el tablero, la fecha de carga y la fuente. Sirve para el encabezado del dashboard sin cambiar el UUID.',
            'fuente' => $hacienda,
            'corte' => 'El mismo corte de v_pasiva_vigente.',
            'columnas' => [
                ...$corteVigente,
                $col('rubros', 'Cantidad de rubros de esa pasiva', $hacienda),
            ],
            'sql' => <<<'SQL'
SELECT fecha_corte, fecha_carga, origen, COUNT(*) AS rubros
FROM {{v_pasiva_vigente}}
GROUP BY fecha_corte, fecha_carga, origen
SQL,
        ],
        [
            'nombre' => 'pasiva_sankey_fuente_sector',
            'dominio' => 'Pasiva',
            'descripcion' => 'Agregado grupo de fuente → sector de la pasiva vigente, para el Sankey. El dataset de Superset debe apuntar a esta vista y no a la tabla: así cada carga nueva actualiza el tablero sin reconstruirlo ni cambiar el UUID.',
            'fuente' => $hacienda,
            'corte' => 'El mismo corte de v_pasiva_vigente. fecha_corte, fecha_carga y origen viajan en cada fila.',
            'columnas' => [
                $col('grupo_fuente', 'Grupo de la fuente', 'Catálogo de fuentes de financiación'),
                $col('sector', 'Sector como código — nombre', $hacienda),
                $col('fuente', 'Fuente como código — nombre', 'Catálogo de fuentes de financiación'),
                ...$montos,
                $col('rubros', 'Cantidad de rubros del grupo, sector y fuente', $hacienda),
                ...$corteVigente,
            ],
            'sql' => <<<'SQL'
SELECT
    grupo_fuente,
    sector,
    fuente,
    SUM(definitiva) AS definitiva,
    SUM(certificado) AS certificado,
    SUM(comprometido) AS comprometido,
    SUM(obligado) AS obligado,
    SUM(pagado) AS pagado,
    COUNT(*) AS rubros,
    fecha_corte,
    fecha_carga,
    origen
FROM {{v_pasiva_vigente}}
GROUP BY grupo_fuente, sector, fuente, fecha_corte, fecha_carga, origen
SQL,
        ],
        [
            'nombre' => 'v_pasiva_por_grupo_fuente',
            'dominio' => 'Pasiva',
            'descripcion' => 'Totales por grupo de fuente de la pasiva vigente. Las columnas originales se mantienen; fecha de corte, fecha de carga y origen van al final.',
            'fuente' => $hacienda,
            'corte' => 'El mismo corte de v_pasiva_vigente.',
            'columnas' => [
                $col('grupo_fuente', 'Grupo de la fuente', 'Catálogo de fuentes de financiación'),
                ...$montos,
                $col('rubros', 'Cantidad de rubros del grupo', $hacienda),
                ...$corteVigente,
            ],
            'sql' => <<<'SQL'
SELECT
    grupo_fuente,
    SUM(definitiva) AS definitiva,
    SUM(certificado) AS certificado,
    SUM(comprometido) AS comprometido,
    SUM(obligado) AS obligado,
    SUM(pagado) AS pagado,
    COUNT(*) AS rubros,
    MIN(fecha_corte) AS fecha_corte,
    MIN(fecha_carga) AS fecha_carga,
    MIN(origen) AS origen
FROM {{v_pasiva_vigente}}
GROUP BY grupo_fuente
SQL,
        ],
        [
            'nombre' => 'v_pasiva_por_sector',
            'dominio' => 'Pasiva',
            'descripcion' => 'Totales por sector, tipo de gasto y grupo de fuente de la pasiva vigente.',
            'fuente' => $hacienda,
            'corte' => 'El mismo corte de v_pasiva_vigente.',
            'columnas' => [
                $col('sector', 'Sector como código — nombre', $hacienda),
                $col('es_inversion', 'Inversión o Funcionamiento / deuda', $hacienda),
                $col('grupo_fuente', 'Grupo de la fuente', 'Catálogo de fuentes de financiación'),
                $col('definitiva', 'Apropiación definitiva', $hacienda),
                $col('comprometido', 'Compromisos', $hacienda),
                $col('obligado', 'Obligaciones', $hacienda),
                $col('pagado', 'Pagos acumulados', $hacienda),
                $col('rubros', 'Cantidad de rubros', $hacienda),
                ...$corteVigente,
            ],
            'sql' => <<<'SQL'
SELECT
    sector,
    es_inversion,
    grupo_fuente,
    SUM(definitiva) AS definitiva,
    SUM(comprometido) AS comprometido,
    SUM(obligado) AS obligado,
    SUM(pagado) AS pagado,
    COUNT(*) AS rubros,
    MIN(fecha_corte) AS fecha_corte,
    MIN(fecha_carga) AS fecha_carga,
    MIN(origen) AS origen
FROM {{v_pasiva_vigente}}
GROUP BY sector, es_inversion, grupo_fuente
SQL,
        ],
        [
            'nombre' => 'v_pasiva_fuente_sector',
            'dominio' => 'Pasiva',
            'descripcion' => 'Totales por sector y fuente de la pasiva vigente. Es la fuente del CSV de datos abiertos.',
            'fuente' => $hacienda,
            'corte' => 'El mismo corte de v_pasiva_vigente.',
            'columnas' => [
                $col('sector', 'Sector como código — nombre', $hacienda),
                $col('grupo_fuente', 'Grupo de la fuente', 'Catálogo de fuentes de financiación'),
                $col('fuente_codigo', 'Código PCT de la fuente', $hacienda),
                $col('fuente', 'Fuente como código — nombre', 'Catálogo de fuentes de financiación'),
                $col('definitiva', 'Apropiación definitiva', $hacienda),
                $col('comprometido', 'Compromisos', $hacienda),
                $col('obligado', 'Obligaciones', $hacienda),
                $col('pagado', 'Pagos acumulados', $hacienda),
                $col('rubros', 'Cantidad de rubros', $hacienda),
                ...$corteVigente,
            ],
            'sql' => <<<'SQL'
SELECT
    sector,
    grupo_fuente,
    fuente_codigo,
    fuente,
    SUM(definitiva) AS definitiva,
    SUM(comprometido) AS comprometido,
    SUM(obligado) AS obligado,
    SUM(pagado) AS pagado,
    COUNT(*) AS rubros,
    MIN(fecha_corte) AS fecha_corte,
    MIN(fecha_carga) AS fecha_carga,
    MIN(origen) AS origen
FROM {{v_pasiva_vigente}}
GROUP BY sector, grupo_fuente, fuente_codigo, fuente
SQL,
        ],
        [
            'nombre' => 'v_fuentes_financiacion',
            'dominio' => 'Fuentes de financiación',
            'descripcion' => 'Catálogo PCT cargado en fuentes_financiacion (el archivo fuentes_financiacion.json trae las 256 fuentes; la vista muestra las que ya están en la base, sin inventar las que falten).',
            'fuente' => 'Catálogo de fuentes de financiación del PCT',
            'corte' => 'No tiene corte. Es un catálogo.',
            'columnas' => [
                $col('codigo', 'Código PCT de la fuente', 'fuentes_financiacion.codigo'),
                $col('fuente', 'Fuente como código — nombre', 'fuentes_financiacion'),
                $col('tipo', 'Tipo oficial de la fuente', 'fuentes_financiacion.tipo'),
                $col('grupo_reporte', 'Grupo usado en el reporte sectorial (recursos_propios, sgr, nacion, otros)', 'fuentes_financiacion.grupo_reporte'),
                $col('activo', 'Sí o No', 'fuentes_financiacion.activo'),
            ],
            'sql' => <<<'SQL'
SELECT
    codigo,
    codigo || ' — ' || nombre AS fuente,
    tipo,
    grupo_reporte,
    CASE WHEN activo THEN 'Sí' ELSE 'No' END AS activo
FROM fuentes_financiacion
WHERE deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_dependencias',
            'dominio' => 'Dependencias y sectores',
            'descripcion' => 'Dependencias del SIID. La etiqueta es la misma que usa el RLS de Superset.',
            'fuente' => 'Catálogo de dependencias del SIID',
            'corte' => 'No tiene corte. Es un catálogo.',
            'columnas' => [
                $col('codigo', 'Código de la dependencia', 'dependencias.codigo'),
                $col('dependencia', 'Dependencia como código — nombre', 'dependencias'),
                $col('sigla', 'Sigla', 'dependencias.sigla'),
                $col('tipo', 'Tipo de dependencia', 'dependencias.tipo'),
                $col('activo', 'Sí o No', 'dependencias.activo'),
            ],
            'sql' => <<<'SQL'
SELECT
    codigo,
    codigo || ' — ' || nombre AS dependencia,
    sigla,
    tipo,
    CASE WHEN activo THEN 'Sí' ELSE 'No' END AS activo
FROM dependencias
WHERE deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_municipios',
            'dominio' => 'Territorio',
            'descripcion' => 'Municipios del Meta registrados en el SIID.',
            'fuente' => 'Catálogo de municipios',
            'corte' => 'No tiene corte. Es un catálogo.',
            'columnas' => [
                $col('codigo_dane', 'Código DANE del municipio', 'municipios.codigo_dane'),
                $col('municipio', 'Municipio como código — nombre', 'municipios'),
                $col('subregion', 'Subregión', 'municipios.subregion'),
            ],
            'sql' => <<<'SQL'
SELECT
    codigo_dane,
    codigo_dane || ' — ' || nombre AS municipio,
    subregion
FROM municipios
SQL,
        ],
        [
            'nombre' => 'v_proyectos_bpin',
            'dominio' => 'Proyectos',
            'descripcion' => 'Proyectos de inversión identificados por BPIN.',
            'fuente' => 'proyectos, creados desde la pasiva o el reporte sectorial',
            'corte' => 'No tiene corte. Es el inventario vigente de proyectos, sin los eliminados.',
            'columnas' => [
                $col('bpin', 'Código BPIN', 'proyectos.bpin'),
                $col('proyecto', 'Proyecto como BPIN — nombre', 'proyectos'),
                $col('municipio', 'Municipio del proyecto como código — nombre, si está diligenciado', 'municipios'),
                $col('activo', 'Sí o No', 'proyectos.activo'),
            ],
            'sql' => <<<'SQL'
SELECT
    p.bpin,
    p.bpin || ' — ' || p.nombre AS proyecto,
    CASE WHEN m.id IS NULL THEN NULL ELSE m.codigo_dane || ' — ' || m.nombre END AS municipio,
    CASE WHEN p.activo THEN 'Sí' ELSE 'No' END AS activo
FROM proyectos p
LEFT JOIN municipios m ON m.id = p.municipio_id
WHERE p.deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_pdd_metas_producto',
            'dominio' => 'Metas y PDD',
            'descripcion' => 'Meta de producto con su cadena del plan de desarrollo, el sector MGA y la dependencia.',
            'fuente' => 'Estructura del Plan de Desarrollo',
            'corte' => 'No tiene corte. Es el catálogo vigente, sin registros eliminados.',
            'columnas' => [
                $col('meta_producto', 'Meta de producto como código — nombre', 'metas_producto'),
                $col('subprograma', 'Subprograma como código — nombre', 'pdd_subprogramas'),
                $col('programa', 'Programa como código — nombre', 'pdd_programas'),
                $col('linea', 'Línea como código — nombre', 'pdd_lineas'),
                $col('eje', 'Eje como código — nombre', 'pdd_ejes'),
                $col('pilar', 'Pilar como código — nombre', 'pdd_pilares'),
                $col('sector_mga', 'Sector MGA como código — nombre', 'sectores_mga'),
                $col('dependencia', 'Dependencia responsable como código — nombre', 'dependencias'),
            ],
            'sql' => <<<'SQL'
SELECT
    mp.codigo || ' — ' || mp.nombre AS meta_producto,
    sp.codigo || ' — ' || sp.nombre AS subprograma,
    pr.codigo || ' — ' || pr.nombre AS programa,
    ln.codigo || ' — ' || ln.nombre AS linea,
    ej.codigo || ' — ' || ej.nombre AS eje,
    pi.codigo || ' — ' || pi.nombre AS pilar,
    sm.codigo || ' — ' || sm.nombre AS sector_mga,
    CASE WHEN d.id IS NULL THEN NULL ELSE d.codigo || ' — ' || d.nombre END AS dependencia
FROM metas_producto mp
JOIN pdd_subprogramas sp ON sp.id = mp.subprograma_id AND sp.deleted_at IS NULL
JOIN pdd_programas pr ON pr.id = sp.programa_id AND pr.deleted_at IS NULL
JOIN pdd_lineas ln ON ln.id = pr.linea_id AND ln.deleted_at IS NULL
JOIN pdd_ejes ej ON ej.id = ln.eje_id AND ej.deleted_at IS NULL
JOIN pdd_pilares pi ON pi.id = ej.pilar_id AND pi.deleted_at IS NULL
JOIN sectores_mga sm ON sm.id = mp.sector_mga_id AND sm.deleted_at IS NULL
LEFT JOIN dependencias d ON d.id = mp.dependencia_id AND d.deleted_at IS NULL
WHERE mp.deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_indicadores_producto',
            'dominio' => 'Metas y PDD',
            'descripcion' => 'Indicador de producto ligado a su meta de producto.',
            'fuente' => 'indicadores_producto',
            'corte' => 'No tiene corte. Es el catálogo vigente.',
            'columnas' => [
                $col('indicador', 'Indicador como código — nombre. Si no tiene código propio, usa el de la meta.', 'indicadores_producto'),
                $col('meta_producto', 'Meta de producto como código — nombre', 'metas_producto'),
                $col('unidad_medida', 'Unidad de medida', 'indicadores_producto.unidad_medida'),
                $col('tipo_acumulacion', 'Tipo de acumulación', 'indicadores_producto.tipo_acumulacion'),
                $col('linea_base', 'Línea base', 'indicadores_producto.linea_base'),
                $col('meta_cuatrienio', 'Meta del cuatrienio', 'indicadores_producto.meta_cuatrienio'),
                $col('fuente_verificacion', 'Fuente de verificación declarada en la ficha', 'indicadores_producto.fuente_verificacion'),
            ],
            'sql' => <<<'SQL'
SELECT
    COALESCE(i.codigo, m.codigo) || ' — ' || i.nombre AS indicador,
    m.codigo || ' — ' || m.nombre AS meta_producto,
    i.unidad_medida,
    i.tipo_acumulacion,
    i.linea_base,
    i.meta_cuatrienio,
    i.fuente_verificacion
FROM indicadores_producto i
JOIN metas_producto m ON m.id = i.meta_producto_id AND m.deleted_at IS NULL
WHERE i.deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_metas_resultado',
            'dominio' => 'Metas y PDD',
            'descripcion' => 'Meta de resultado con su programa y su indicador de resultado, cuando existen.',
            'fuente' => 'metas_resultado',
            'corte' => 'No tiene corte. Es el catálogo vigente.',
            'columnas' => [
                $col('meta_resultado', 'Meta de resultado como código — descripción. Usa el código provisional si el definitivo aún no existe.', 'metas_resultado'),
                $col('programa', 'Programa como código — nombre', 'pdd_programas'),
                $col('indicador_resultado', 'Indicador de resultado como código — nombre', 'indicadores_resultado'),
                $col('linea_base', 'Línea base de la meta', 'metas_resultado.linea_base'),
                $col('meta_cuatrienio', 'Meta del cuatrienio', 'metas_resultado.meta_cuatrienio'),
            ],
            'sql' => <<<'SQL'
SELECT
    COALESCE(mr.codigo, mr.codigo_provisional) || ' — ' || mr.descripcion AS meta_resultado,
    CASE WHEN pr.id IS NULL THEN NULL ELSE pr.codigo || ' — ' || pr.nombre END AS programa,
    CASE WHEN ir.id IS NULL THEN NULL ELSE COALESCE(ir.codigo, '') || ' — ' || ir.nombre END AS indicador_resultado,
    mr.linea_base,
    mr.meta_cuatrienio
FROM metas_resultado mr
LEFT JOIN pdd_programas pr ON pr.id = mr.programa_id AND pr.deleted_at IS NULL
LEFT JOIN indicadores_resultado ir ON ir.id = mr.indicador_resultado_id AND ir.deleted_at IS NULL
WHERE mr.deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_seguimiento_cuatrienio',
            'dominio' => 'Seguimiento',
            'descripcion' => 'Seguimiento cuatrienal por meta de producto (seguimiento_4m_metas).',
            'fuente' => 'Consolidado cuatrienal cargado en seguimiento_4m_metas',
            'corte' => 'La columna vigencia es el año del consolidado. No hay una fecha de corte diaria en esa tabla.',
            'columnas' => [
                $col('vigencia', 'Año del consolidado', 'seguimiento_4m_metas.vigencia'),
                $col('meta_producto', 'Meta como código — nombre. Si la meta no está en el catálogo: código — Sin catálogo', 'metas_producto'),
                $col('responsable', 'Responsable reportado en el consolidado', 'seguimiento_4m_metas.responsable'),
                $col('programado_fisico', 'Cantidad programada', 'seguimiento_4m_metas.programado_fisico'),
                $col('ejecutado_fisico', 'Cantidad ejecutada', 'seguimiento_4m_metas.ejecutado_fisico'),
                $col('asignado', 'Valor asignado', 'seguimiento_4m_metas.asignado'),
                $col('comprometido', 'Valor comprometido', 'seguimiento_4m_metas.comprometido'),
                $col('obligado', 'Valor obligado', 'seguimiento_4m_metas.obligado'),
                $col('pagado', 'Valor pagado', 'seguimiento_4m_metas.pagado'),
                $col('estado_obligado', 'Estado del reporte de obligado', 'seguimiento_4m_metas.estado_obligado'),
                $col('estado_pagado', 'Estado del reporte de pagado', 'seguimiento_4m_metas.estado_pagado'),
                $col('fuente', 'Origen del consolidado declarado en la fila (por ejemplo consolidado_4m)', 'seguimiento_4m_metas.fuente'),
            ],
            'sql' => <<<'SQL'
SELECT
    c.vigencia,
    c.codigo_meta_producto || ' — ' || COALESCE(m.nombre, 'Sin catálogo') AS meta_producto,
    c.responsable,
    c.programado_fisico,
    c.ejecutado_fisico,
    c.asignado,
    c.comprometido,
    c.obligado,
    c.pagado,
    c.estado_obligado,
    c.estado_pagado,
    c.fuente
FROM seguimiento_4m_metas c
LEFT JOIN metas_producto m ON m.id = c.meta_producto_id AND m.deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_plan_indicativo',
            'dominio' => 'Metas y PDD',
            'descripcion' => 'Programación anual de las metas de producto.',
            'fuente' => 'plan_indicativo_metas',
            'corte' => 'La columna vigencia es el año programado.',
            'columnas' => [
                $col('meta_producto', 'Meta de producto como código — nombre', 'metas_producto'),
                $col('vigencia', 'Año programado', 'plan_indicativo_metas.vigencia'),
                $col('valor_programado', 'Valor o cantidad programada', 'plan_indicativo_metas.valor_programado'),
                $col('observacion', 'Observación del plan', 'plan_indicativo_metas.observacion'),
            ],
            'sql' => <<<'SQL'
SELECT
    m.codigo || ' — ' || m.nombre AS meta_producto,
    p.vigencia,
    p.valor_programado,
    p.observacion
FROM plan_indicativo_metas p
JOIN metas_producto m ON m.id = p.meta_producto_id AND m.deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_pasiva_lineas_vigentes',
            'dominio' => 'Pasiva',
            'descripcion' => 'Líneas de la pasiva operativa cuya carga sigue vigente en cada seguimiento. Una recarga del mismo seguimiento deja la anterior en pasiva_cargas (es_vigente en falso) y no la borra; esta vista solo muestra la vigente de cada corte.',
            'fuente' => $hacienda,
            'corte' => 'seguimientos.fecha_corte de cada carga vigente.',
            'columnas' => [
                $col('fecha_corte', 'Fecha de corte del seguimiento', 'seguimientos.fecha_corte'),
                $col('vigencia', 'Año del seguimiento', 'seguimientos.vigencia'),
                $col('mes', 'Mes del seguimiento', 'seguimientos.mes'),
                $col('rubro', 'Rubro presupuestal', 'pasiva_lineas.rubro'),
                $col('concepto', 'Descripción de la línea', 'pasiva_lineas.concepto'),
                $col('fuente', 'Fuente como código — nombre, o código — Sin catálogo', 'fuentes_financiacion'),
                $col('dependencia', 'Dependencia como código — nombre, si ya se resolvió', 'dependencias'),
                $col('bpin', 'BPIN de la línea, o Sin BPIN', 'pasiva_lineas.bpin'),
                $col('nombre_proyecto', 'Nombre de proyecto leído en la pasiva', 'pasiva_lineas.nombre_proyecto'),
                $col('definitiva', 'Apropiación definitiva', 'pasiva_lineas.apropiacion_definitiva'),
                $col('certificado', 'CDP', 'pasiva_lineas.cdp'),
                $col('comprometido', 'Compromisos', 'pasiva_lineas.compromisos'),
                $col('obligado', 'Obligaciones', 'pasiva_lineas.obligaciones'),
                $col('pagado', 'Pagos acumulados de la línea. No es un pago individual por BPIN.', 'pasiva_lineas.pagos'),
                $col('estado_revision', 'asignada, pendiente o no aplica', 'pasiva_lineas.estado_revision'),
                $col('archivo', 'Nombre original del archivo de la carga vigente', 'pasiva_cargas.nombre_original'),
            ],
            'sql' => <<<'SQL'
SELECT
    s.fecha_corte,
    s.vigencia,
    s.mes,
    l.rubro,
    l.concepto,
    CASE
        WHEN f.id IS NULL THEN l.codigo_fuente || ' — Sin catálogo'
        ELSE f.codigo || ' — ' || f.nombre
    END AS fuente,
    CASE WHEN d.id IS NULL THEN NULL ELSE d.codigo || ' — ' || d.nombre END AS dependencia,
    CASE WHEN l.bpin IS NULL OR l.bpin = '' THEN 'Sin BPIN' ELSE l.bpin END AS bpin,
    l.nombre_proyecto,
    l.apropiacion_definitiva AS definitiva,
    l.cdp AS certificado,
    l.compromisos AS comprometido,
    l.obligaciones AS obligado,
    l.pagos AS pagado,
    l.estado_revision,
    c.nombre_original AS archivo
FROM pasiva_lineas l
JOIN pasiva_cargas c ON c.id = l.pasiva_carga_id AND c.es_vigente = true
JOIN seguimientos s ON s.id = l.seguimiento_id
LEFT JOIN fuentes_financiacion f ON f.id = l.fuente_financiacion_id AND f.deleted_at IS NULL
LEFT JOIN dependencias d ON d.id = l.dependencia_id AND d.deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_techos',
            'dominio' => 'Ejecución',
            'descripcion' => 'Techos calculados por proyecto, fuente y dependencia en cada seguimiento.',
            'fuente' => 'Techos del reporte sectorial, derivados de la pasiva vigente del corte',
            'corte' => 'seguimientos.fecha_corte.',
            'columnas' => [
                $col('fecha_corte', 'Fecha de corte del seguimiento', 'seguimientos.fecha_corte'),
                $col('proyecto', 'Proyecto como BPIN — nombre', 'proyectos'),
                $col('fuente', 'Fuente como código — nombre', 'fuentes_financiacion'),
                $col('dependencia', 'Dependencia como código — nombre', 'dependencias'),
                $col('valor_pasiva', 'Valor tomado de la pasiva', 'techos.valor_pasiva'),
                $col('valor_ajuste', 'Ajuste manual, si existe', 'techos.valor_ajuste'),
                $col('techo', 'Techo resultante', 'techos.valor'),
            ],
            'sql' => <<<'SQL'
SELECT
    s.fecha_corte,
    p.bpin || ' — ' || p.nombre AS proyecto,
    f.codigo || ' — ' || f.nombre AS fuente,
    d.codigo || ' — ' || d.nombre AS dependencia,
    t.valor_pasiva,
    t.valor_ajuste,
    t.valor AS techo
FROM techos t
JOIN seguimientos s ON s.id = t.seguimiento_id
JOIN proyectos p ON p.id = t.proyecto_id AND p.deleted_at IS NULL
JOIN fuentes_financiacion f ON f.id = t.fuente_financiacion_id AND f.deleted_at IS NULL
JOIN dependencias d ON d.id = t.dependencia_id AND d.deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_ejecucion_financiera',
            'dominio' => 'Ejecución',
            'descripcion' => 'Ejecución financiera reportada por actividad, fuente, proyecto y dependencia.',
            'fuente' => 'ejecuciones_financieras del reporte sectorial',
            'corte' => 'seguimientos.fecha_corte del reporte.',
            'columnas' => [
                $col('fecha_corte', 'Fecha de corte del seguimiento', 'seguimientos.fecha_corte'),
                $col('vigencia', 'Año del seguimiento', 'seguimientos.vigencia'),
                $col('mes', 'Mes del seguimiento', 'seguimientos.mes'),
                $col('proyecto', 'Proyecto como BPIN — nombre', 'proyectos'),
                $col('dependencia', 'Dependencia como código — nombre', 'dependencias'),
                $col('fuente', 'Fuente como código — nombre', 'fuentes_financiacion'),
                $col('actividad', 'Nombre de la actividad', 'actividades.nombre'),
                $col('comprometido', 'Comprometido reportado', 'ejecuciones_financieras.comprometido'),
                $col('obligado', 'Obligado reportado', 'ejecuciones_financieras.obligado'),
                $col('pagado', 'Pagado reportado', 'ejecuciones_financieras.pagado'),
                $col('estado_reporte', 'Estado del reporte de proyecto', 'reportes_proyecto.estado'),
            ],
            'sql' => <<<'SQL'
SELECT
    s.fecha_corte,
    s.vigencia,
    s.mes,
    p.bpin || ' — ' || p.nombre AS proyecto,
    d.codigo || ' — ' || d.nombre AS dependencia,
    f.codigo || ' — ' || f.nombre AS fuente,
    a.nombre AS actividad,
    e.comprometido,
    e.obligado,
    e.pagado,
    r.estado AS estado_reporte
FROM ejecuciones_financieras e
JOIN reportes_proyecto r ON r.id = e.reporte_proyecto_id
JOIN seguimientos s ON s.id = r.seguimiento_id
JOIN proyectos p ON p.id = r.proyecto_id AND p.deleted_at IS NULL
JOIN dependencias d ON d.id = r.dependencia_id AND d.deleted_at IS NULL
JOIN fuentes_financiacion f ON f.id = e.fuente_financiacion_id AND f.deleted_at IS NULL
JOIN actividades a ON a.id = e.actividad_id AND a.deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_avance_fisico',
            'dominio' => 'Ejecución',
            'descripcion' => 'Avance físico reportado por actividad y proyecto.',
            'fuente' => 'avances_fisicos del reporte sectorial',
            'corte' => 'seguimientos.fecha_corte. fecha_ejecucion es la fecha declarada en el avance, si existe.',
            'columnas' => [
                $col('fecha_corte', 'Fecha de corte del seguimiento', 'seguimientos.fecha_corte'),
                $col('proyecto', 'Proyecto como BPIN — nombre', 'proyectos'),
                $col('dependencia', 'Dependencia como código — nombre', 'dependencias'),
                $col('actividad', 'Nombre de la actividad', 'actividades.nombre'),
                $col('cantidad', 'Cantidad ejecutada reportada', 'avances_fisicos.cantidad'),
                $col('fecha_ejecucion', 'Fecha de ejecución declarada', 'avances_fisicos.fecha_ejecucion'),
                $col('descripcion', 'Descripción del avance', 'avances_fisicos.descripcion'),
            ],
            'sql' => <<<'SQL'
SELECT
    s.fecha_corte,
    p.bpin || ' — ' || p.nombre AS proyecto,
    d.codigo || ' — ' || d.nombre AS dependencia,
    a.nombre AS actividad,
    v.cantidad,
    v.fecha_ejecucion,
    v.descripcion
FROM avances_fisicos v
JOIN reportes_proyecto r ON r.id = v.reporte_proyecto_id
JOIN seguimientos s ON s.id = r.seguimiento_id
JOIN proyectos p ON p.id = r.proyecto_id AND p.deleted_at IS NULL
JOIN dependencias d ON d.id = r.dependencia_id AND d.deleted_at IS NULL
JOIN actividades a ON a.id = v.actividad_id AND a.deleted_at IS NULL
SQL,
        ],
        [
            'nombre' => 'v_estudios',
            'dominio' => 'Estudios',
            'descripcion' => 'Estudios y publicaciones cargados en el CMS del micrositio. El sitio público muestra la instantánea publicada; esta vista lee el borrador del CMS, incluidas las filas marcadas como ejemplo.',
            'fuente' => 'micrositio_publicaciones',
            'corte' => 'La columna fecha es la fecha editorial de la publicación, no un corte de pasiva.',
            'columnas' => [
                $col('tipo', 'Tipo de publicación (boletín, estudio, infografía)', 'micrositio_publicaciones.tipo'),
                $col('titulo', 'Título', 'micrositio_publicaciones.titulo'),
                $col('fecha', 'Fecha editorial', 'micrositio_publicaciones.fecha'),
                $col('resumen', 'Resumen', 'micrositio_publicaciones.resumen'),
                $col('es_ejemplo', 'Sí cuando la fila está marcada como dato de ejemplo', 'micrositio_publicaciones.es_ejemplo'),
                $col('nombre_archivo', 'Nombre original del archivo', 'micrositio_publicaciones.nombre_archivo'),
            ],
            'sql' => <<<'SQL'
SELECT
    tipo,
    titulo,
    fecha,
    resumen,
    CASE WHEN es_ejemplo THEN 'Sí' ELSE 'No' END AS es_ejemplo,
    nombre_archivo
FROM micrositio_publicaciones
SQL,
        ],
        [
            'nombre' => 'v_geodatos_conjuntos',
            'dominio' => 'Geodatos',
            'descripcion' => 'Catálogo de conjuntos espaciales del SIID. No incluye geometrías.',
            'fuente' => 'spatial_datasets',
            'corte' => 'No tiene corte.',
            'columnas' => [
                $col('nombre', 'Nombre del conjunto', 'spatial_datasets.name'),
                $col('slug', 'Identificador público', 'spatial_datasets.slug'),
                $col('sector', 'Sector declarado en el conjunto', 'spatial_datasets.sector'),
                $col('tipo_geometria', 'Tipo de geometría', 'spatial_datasets.geometry_type'),
                $col('estado', 'Estado de publicación del conjunto', 'spatial_datasets.status'),
                $col('descripcion', 'Descripción', 'spatial_datasets.description'),
            ],
            'sql' => <<<'SQL'
SELECT
    name AS nombre,
    slug,
    sector,
    geometry_type AS tipo_geometria,
    status AS estado,
    description AS descripcion
FROM spatial_datasets
SQL,
        ],
        [
            'nombre' => 'v_indicadores_siid',
            'dominio' => 'Indicadores',
            'descripcion' => 'Indicadores del módulo de indicadores que ya están publicados. No incluye el JSON de metadatos ni borradores.',
            'fuente' => 'indicators',
            'corte' => 'publicado_en es la fecha de publicación del indicador, cuando existe.',
            'columnas' => [
                $col('nombre', 'Nombre del indicador', 'indicators.name'),
                $col('slug', 'Identificador público', 'indicators.slug'),
                $col('sector', 'Sector declarado', 'indicators.sector'),
                $col('unidad', 'Unidad', 'indicators.unit'),
                $col('periodicidad', 'Periodicidad', 'indicators.periodicity'),
                $col('tipo_fuente', 'Tipo de fuente de datos', 'indicators.source_type'),
                $col('estado', 'Estado. Esta vista solo trae published.', 'indicators.status'),
                $col('publicado_en', 'Fecha de publicación', 'indicators.published_at'),
            ],
            'sql' => <<<'SQL'
SELECT
    name AS nombre,
    slug,
    sector,
    unit AS unidad,
    periodicity AS periodicidad,
    source_type AS tipo_fuente,
    status AS estado,
    published_at AS publicado_en
FROM indicators
WHERE status = 'published'
SQL,
        ],
    ],
];
