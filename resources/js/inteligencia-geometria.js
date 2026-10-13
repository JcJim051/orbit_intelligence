export const TEXTO_SIN_AREA = 'dibuje o cargue un área';

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
    const mensaje = opciones.mensaje ?? null;

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
        };
    }

    const areaHa = formatearHectareas(areaHectareasDeGeometria(geometria, sridOrigen));

    return {
        geojson: JSON.stringify(geometria),
        origen,
        sridOrigen,
        nombreArchivo,
        areaHa,
        mensaje,
        textoArea: `${areaHa} ha`,
        puedeProcesar: true,
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
