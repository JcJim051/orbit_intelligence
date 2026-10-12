export function areaHectareas(anillos) {
    return anillos.reduce((total, anillo) => total + Math.abs(areaDeAnillo(anillo)), 0) / 10000;
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
