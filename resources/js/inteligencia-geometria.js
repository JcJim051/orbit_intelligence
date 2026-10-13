export const TEXTO_SIN_AREA = 'dibuje o cargue un área';

export const MENSAJE_TOTALMENTE_FUERA = 'El polígono está por fuera del Meta. Las capas solo cubren el departamento, así que no hay área para analizar.';

export function areaHectareas(anillos) {
    return anillos.reduce((total, anillo) => total + Math.abs(areaDeAnillo(anillo)), 0) / 10000;
}

export function formatearHectareas(valor) {
    const negativo = valor < 0;
    const [entero, decimal] = Math.abs(valor).toFixed(2).split('.');
    const grupos = entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${negativo ? '-' : ''}${grupos},${decimal}`;
}

export function areaHectareasDeGeometria(geometria, srid = 4326) {
    const anillos = anillosExteriores(geometria);

    if (Number(srid) === 9377) {
        return anillos.reduce((total, anillo) => total + Math.abs(areaPlana(anillo)), 0) / 10000;
    }

    return areaHectareas(anillos);
}

export function estadoDeGeometria(geometria, opciones = {}) {
    const origen = opciones.origen ?? 'dibujo';
    const sridOrigen = opciones.srid ?? 4326;
    const nombreArchivo = opciones.nombreArchivo ?? null;
    let mensaje = opciones.mensaje ?? null;

    if (! geometria) {
        return {
            geojson: null,
            origen,
            sridOrigen,
            nombreArchivo,
            areaHa: null,
            mensaje,
            textoArea: TEXTO_SIN_AREA,
            puedeProcesar: false,
            geometriaFuera: null,
            haFuera: 0,
            pctFuera: 0,
        };
    }

    const hectareas = areaHectareasDeGeometria(geometria, sridOrigen);
    const areaHa = formatearHectareas(hectareas);
    let geometriaFuera = null;
    let haFuera = 0;
    let pctFuera = 0;
    let puedeProcesar = true;

    if (opciones.limite && Number(sridOrigen) !== 9377) {
        const recorte = recortarAlLimite(geometria, opciones.limite);

        if (recorte && recorte.pctFuera >= 99.9) {
            puedeProcesar = false;
            haFuera = recorte.haFuera;
            pctFuera = recorte.pctFuera;
            geometriaFuera = geometria;
            mensaje = MENSAJE_TOTALMENTE_FUERA;
        } else if (recorte && recorte.pctFuera >= 0.05) {
            haFuera = recorte.haFuera;
            pctFuera = recorte.pctFuera;
            geometriaFuera = recorte.geometriaFuera;
            mensaje = `Quedaron fuera del Meta ${formatearHectareas(haFuera)} ha (${formatearPorcentaje(pctFuera)}%). Esa parte no se analizará porque las capas cubren solo el Meta.`;
        }
    }

    return {
        geojson: JSON.stringify(geometria),
        origen,
        sridOrigen,
        nombreArchivo,
        areaHa,
        mensaje,
        textoArea: `${areaHa} ha`,
        puedeProcesar,
        geometriaFuera,
        haFuera,
        pctFuera,
    };
}

export function formatearPorcentaje(valor) {
    const [entero, decimal] = Math.abs(valor).toFixed(2).split('.');
    const grupos = entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${grupos},${decimal}`;
}

/**
 * Parte del polígono que cae fuera del anillo de referencia.
 * El anillo oficial simplificado sirve para avisar; el servidor confirma con el límite completo.
 *
 * @param {object} geometria
 * @param {object} limite
 * @returns {{geometriaFuera: object|null, haFuera: number, pctFuera: number}|null}
 */
export function recortarAlLimite(geometria, limite) {
    const sujetos = anillosExteriores(geometria);
    const clips = anillosExteriores(limite);

    if (sujetos.length === 0 || clips.length === 0) {
        return null;
    }

    const clip = normalizarAnillo(clips[0]);
    const piezas = [];
    let haSujeto = 0;
    let haFuera = 0;

    for (const sujeto of sujetos) {
        const anillo = normalizarAnillo(sujeto);
        const parcial = diferenciaDeAnillos(anillo, clip);
        haSujeto += areaHectareas([cerrarAnillo(anillo)]);
        haFuera += parcial.hectareas;
        piezas.push(...parcial.piezas);
    }

    const pctFuera = haSujeto > 0 ? (haFuera / haSujeto) * 100 : 0;

    return {
        geometriaFuera: geometriaDeAnillos(piezas),
        haFuera,
        pctFuera,
    };
}

export function aplicarEstado(panel, wire, estado) {
    panel.textoArea = estado.textoArea;
    panel.puedeProcesar = estado.puedeProcesar;
    panel.mensaje = estado.mensaje;

    if (! wire || typeof wire.set !== 'function') {
        return { ...estado, sincronizado: false };
    }

    wire.set('geojson', estado.geojson, false);
    wire.set('nombreArchivo', estado.nombreArchivo, false);
    wire.set('origen', estado.origen, false);
    wire.set('sridOrigen', estado.sridOrigen, false);
    wire.set('areaHa', estado.areaHa, false);
    wire.set('mensaje', estado.mensaje, false);
    wire.commit?.();

    return { ...estado, sincronizado: true };
}

export function publicarColeccion(coleccion, panel, wire, opciones = {}) {
    return aplicarEstado(panel, wire, estadoDeGeometria(geometriaDePoligonos(coleccion), opciones));
}

export function geometriaDePoligonos(geojson) {
    const poligonos = poligonosDe(geojson);

    if (poligonos.length === 0) {
        return null;
    }

    if (poligonos.length === 1) {
        return { type: 'Polygon', coordinates: poligonos[0] };
    }

    return { type: 'MultiPolygon', coordinates: poligonos };
}

export function sridDeArchivo(nombre, prj, geojson) {
    const texto = `${nombre ?? ''} ${prj ?? ''}`;

    if (texto.includes('9377')) {
        return 9377;
    }

    if (/4326|WGS_1984|GCS_WGS/i.test(texto)) {
        return 4326;
    }

    const coordenada = primeraCoordenada(geojson);

    if (! coordenada) {
        return 4326;
    }

    if (Math.abs(coordenada[0]) > 180 || Math.abs(coordenada[1]) > 90) {
        return null;
    }

    return 4326;
}

export function origenDeNombre(nombre) {
    const extension = (nombre ?? '').split('.').pop()?.toLowerCase();

    if (extension === 'kml') {
        return 'kml';
    }

    if (extension === 'kmz') {
        return 'kmz';
    }

    if (extension === 'zip') {
        return 'shapefile';
    }

    if (extension === 'geojson' || extension === 'json') {
        return 'geojson';
    }

    return 'dibujo';
}

function diferenciaDeAnillos(sujeto, clip) {
    if (! hayCruces(sujeto, clip)) {
        return diferenciaSinCruces(sujeto, clip);
    }

    try {
        const anillos = greinerHormann(sujeto, clip);
        const hectareas = anillos.reduce((total, anillo) => total + areaHectareas([cerrarAnillo(anillo)]), 0);

        return { piezas: anillos.map((anillo) => [cerrarAnillo(anillo)]), hectareas };
    } catch {
        return diferenciaSinCruces(sujeto, clip);
    }
}

function diferenciaSinCruces(sujeto, clip) {
    const sujetoDentro = puntoDentro(centroide(sujeto), clip);
    const clipDentro = puntoDentro(centroide(clip), sujeto);
    const cerrado = cerrarAnillo(sujeto);
    const haSujeto = areaHectareas([cerrado]);

    if (sujetoDentro && clipDentro) {
        const contiene = Math.abs(areaPlana(cerrado)) > Math.abs(areaPlana(cerrarAnillo(clip))) * 1.001;

        if (! contiene) {
            return { piezas: [], hectareas: 0 };
        }
    } else if (sujetoDentro) {
        return { piezas: [], hectareas: 0 };
    } else if (! clipDentro) {
        return { piezas: [[cerrado]], hectareas: haSujeto };
    }

    return {
        piezas: [[cerrado, cerrarAnillo(invertirAnillo(clip))]],
        hectareas: Math.max(0, haSujeto - areaHectareas([cerrarAnillo(clip)])),
    };
}

function greinerHormann(sujeto, clip) {
    const listaSujeto = listaCircular(sujeto);
    const listaClip = listaCircular(clip);
    insertarCruces(listaSujeto, listaClip);
    marcarEntradas(listaSujeto, clip, true);
    marcarEntradas(listaClip, sujeto, false);

    return recorrer(listaSujeto, listaClip);
}

function listaCircular(anillo) {
    const nodos = abrirAnillo(anillo).map(([x, y]) => nodo(x, y, false, 0));
    nodos.forEach((actual, indice) => {
        actual.next = nodos[(indice + 1) % nodos.length];
        actual.prev = nodos[(indice - 1 + nodos.length) % nodos.length];
    });

    return nodos[0];
}

function nodo(x, y, intersect, alpha) {
    return {
        x, y, next: null, prev: null, intersect, alpha, entry: false, visitado: false, vecino: null,
    };
}

function insertarCruces(sujeto, clip) {
    let origen = sujeto;

    do {
        if (! origen.intersect) {
            const fin = verticeSiguiente(origen);
            let otro = clip;

            do {
                if (! otro.intersect) {
                    const finOtro = verticeSiguiente(otro);
                    const cruce = cruceDeSegmentos(
                        [origen.x, origen.y],
                        [fin.x, fin.y],
                        [otro.x, otro.y],
                        [finOtro.x, finOtro.y],
                    );

                    if (cruce) {
                        const enSujeto = insertarEntre(origen, fin, cruce.t, cruce.punto[0], cruce.punto[1]);
                        const enClip = insertarEntre(otro, finOtro, cruce.u, cruce.punto[0], cruce.punto[1]);
                        enSujeto.vecino = enClip;
                        enClip.vecino = enSujeto;
                    }
                }

                otro = otro.next;
            } while (otro !== clip);
        }

        origen = origen.next;
    } while (origen !== sujeto);
}

function verticeSiguiente(actual) {
    let cursor = actual.next;

    while (cursor.intersect) {
        cursor = cursor.next;
    }

    return cursor;
}

function insertarEntre(inicio, fin, alpha, x, y) {
    let actual = inicio;

    while (actual.next !== fin && actual.next.alpha < alpha) {
        actual = actual.next;
    }

    const creado = nodo(x, y, true, alpha);
    creado.next = actual.next;
    creado.prev = actual;
    actual.next.prev = creado;
    actual.next = creado;

    return creado;
}

function marcarEntradas(poligono, otroAnillo, diferencia) {
    let actual = poligono;

    while (actual.intersect) {
        actual = actual.next;
    }

    const primero = actual;
    let adentro = puntoDentro([primero.x, primero.y], otroAnillo);

    do {
        if (actual.intersect) {
            actual.entry = diferencia ? adentro : ! adentro;
            adentro = ! adentro;
        }

        actual = actual.next;
    } while (actual !== primero);
}

function recorrer(sujeto, clip) {
    const anillos = [];

    for (const inicio of [sujeto, clip]) {
        let actual = inicio;

        do {
            if (actual.intersect && actual.entry && ! actual.visitado) {
                anillos.push(extraerAnillo(actual));
            }

            actual = actual.next;
        } while (actual !== inicio);
    }

    return anillos.filter((anillo) => anillo.length >= 3);
}

function extraerAnillo(inicio) {
    const anillo = [];
    let actual = inicio;
    let pasos = 0;

    do {
        if (++pasos > 8000) {
            throw new Error('recorte');
        }

        actual.visitado = true;

        if (actual.vecino) {
            actual.vecino.visitado = true;
        }

        anillo.push([actual.x, actual.y]);

        if (actual.entry) {
            do {
                actual = actual.next;
                anillo.push([actual.x, actual.y]);
            } while (! actual.intersect);
        } else {
            do {
                actual = actual.prev;
                anillo.push([actual.x, actual.y]);
            } while (! actual.intersect);
        }

        actual = actual.vecino;
    } while (actual && ! actual.visitado);

    return deduplicar(anillo);
}

function hayCruces(sujeto, clip) {
    const a = abrirAnillo(sujeto);
    const b = abrirAnillo(clip);

    for (let i = 0; i < a.length; i += 1) {
        const a2 = a[(i + 1) % a.length];

        for (let j = 0; j < b.length; j += 1) {
            if (cruceDeSegmentos(a[i], a2, b[j], b[(j + 1) % b.length])) {
                return true;
            }
        }
    }

    return false;
}

function cruceDeSegmentos(a, b, c, d) {
    const rx = b[0] - a[0];
    const ry = b[1] - a[1];
    const sx = d[0] - c[0];
    const sy = d[1] - c[1];
    const den = (rx * sy) - (ry * sx);

    if (Math.abs(den) < 1e-12) {
        return null;
    }

    const qpx = c[0] - a[0];
    const qpy = c[1] - a[1];
    const t = ((qpx * sy) - (qpy * sx)) / den;
    const u = ((qpx * ry) - (qpy * rx)) / den;
    const eps = 1e-9;

    if (t <= eps || t >= 1 - eps || u <= eps || u >= 1 - eps) {
        return null;
    }

    return { punto: [a[0] + (t * rx), a[1] + (t * ry)], t, u };
}

function puntoDentro(punto, anillo) {
    const pts = abrirAnillo(anillo);
    let adentro = false;

    for (let i = 0, j = pts.length - 1; i < pts.length; j = i, i += 1) {
        const xi = pts[i][0];
        const yi = pts[i][1];
        const xj = pts[j][0];
        const yj = pts[j][1];

        if ((yi > punto[1]) !== (yj > punto[1]) && punto[0] < (((xj - xi) * (punto[1] - yi)) / (yj - yi)) + xi) {
            adentro = ! adentro;
        }
    }

    return adentro;
}

function centroide(anillo) {
    const pts = abrirAnillo(anillo);
    const suma = pts.reduce((total, punto) => [total[0] + punto[0], total[1] + punto[1]], [0, 0]);

    return [suma[0] / pts.length, suma[1] / pts.length];
}

function normalizarAnillo(anillo) {
    const abierto = abrirAnillo(anillo);

    if (areaPlana(cerrarAnillo(abierto)) < 0) {
        abierto.reverse();
    }

    return abierto;
}

function invertirAnillo(anillo) {
    return abrirAnillo(anillo).reverse();
}

function abrirAnillo(anillo) {
    const copia = anillo.map((punto) => [punto[0], punto[1]]);

    while (copia.length > 1 && mismosPuntos(copia[0], copia[copia.length - 1])) {
        copia.pop();
    }

    return copia.filter((punto, indice) => indice === 0 || ! mismosPuntos(punto, copia[indice - 1]));
}

function cerrarAnillo(anillo) {
    const copia = abrirAnillo(anillo).map((punto) => [punto[0], punto[1]]);

    if (copia.length > 0) {
        copia.push([copia[0][0], copia[0][1]]);
    }

    return copia;
}

function deduplicar(anillo) {
    return anillo.filter((punto, indice) => indice === 0 || ! mismosPuntos(punto, anillo[indice - 1]));
}

function mismosPuntos(a, b) {
    return Math.abs(a[0] - b[0]) <= 1e-9 && Math.abs(a[1] - b[1]) <= 1e-9;
}

function geometriaDeAnillos(piezas) {
    const validas = piezas.filter((pieza) => pieza[0] && pieza[0].length >= 4);

    if (validas.length === 0) {
        return null;
    }

    if (validas.length === 1) {
        return { type: 'Polygon', coordinates: validas[0] };
    }

    return { type: 'MultiPolygon', coordinates: validas };
}

function anillosExteriores(geometria) {
    if (! geometria) {
        return [];
    }

    if (geometria.type === 'Polygon') {
        return [geometria.coordinates[0]];
    }

    if (geometria.type === 'MultiPolygon') {
        return geometria.coordinates.map((poligono) => poligono[0]);
    }

    return [];
}

function areaPlana(anillo) {
    if (! Array.isArray(anillo) || anillo.length < 4) {
        return 0;
    }

    let total = 0;

    for (let indice = 0; indice < anillo.length - 1; indice += 1) {
        const [x1, y1] = anillo[indice];
        const [x2, y2] = anillo[indice + 1];
        total += (x1 * y2) - (x2 * y1);
    }

    return total / 2;
}

function areaDeAnillo(anillo) {
    if (! Array.isArray(anillo) || anillo.length < 4) {
        return 0;
    }

    const radio = 6378137;
    let total = 0;

    for (let indice = 0; indice < anillo.length - 1; indice += 1) {
        const [lon1, lat1] = anillo[indice];
        const [lon2, lat2] = anillo[indice + 1];
        total += ((lon2 - lon1) * Math.PI) / 180 * (2 + Math.sin((lat1 * Math.PI) / 180) + Math.sin((lat2 * Math.PI) / 180));
    }

    return (total * radio * radio) / 2;
}

function poligonosDe(nodo) {
    if (! nodo || typeof nodo !== 'object') {
        return [];
    }

    if (nodo.type === 'FeatureCollection') {
        return (nodo.features ?? []).flatMap(poligonosDe);
    }

    if (nodo.type === 'Feature') {
        return poligonosDe(nodo.geometry);
    }

    if (nodo.type === 'GeometryCollection') {
        return (nodo.geometries ?? []).flatMap(poligonosDe);
    }

    if (nodo.type === 'Polygon' && Array.isArray(nodo.coordinates)) {
        return [nodo.coordinates];
    }

    if (nodo.type === 'MultiPolygon' && Array.isArray(nodo.coordinates)) {
        return nodo.coordinates;
    }

    return [];
}

function primeraCoordenada(nodo) {
    if (! nodo) {
        return null;
    }

    if (Array.isArray(nodo) && nodo.length >= 2 && typeof nodo[0] === 'number') {
        return nodo;
    }

    if (Array.isArray(nodo)) {
        for (const hijo of nodo) {
            const encontrada = primeraCoordenada(hijo);

            if (encontrada) {
                return encontrada;
            }
        }

        return null;
    }

    if (typeof nodo === 'object') {
        return primeraCoordenada(nodo.coordinates ?? nodo.geometry ?? nodo.features ?? nodo.geometries);
    }

    return null;
}
