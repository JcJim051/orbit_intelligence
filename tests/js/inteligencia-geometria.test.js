import assert from 'node:assert/strict';
import test from 'node:test';
import { areaHectareas, aplicarEstado, estadoDeGeometria, formatearHectareas, geometriaDePoligonos, MENSAJE_TOTALMENTE_FUERA, origenDeNombre, publicarColeccion, sridDeArchivo } from '../../resources/js/inteligencia-geometria.js';

test('un cuadrado cerca de Villavicencio informa hectáreas y conserva el polígono', () => {
    const geometria = geometriaDePoligonos({
        type: 'FeatureCollection',
        features: [{
            type: 'Feature',
            geometry: {
                type: 'Polygon',
                coordinates: [[[-73.78, 3.97], [-73.75, 3.97], [-73.75, 4.00], [-73.78, 4.00], [-73.78, 3.97]]],
            },
        }],
    });

    assert.equal(geometria.type, 'Polygon');
    assert.ok(areaHectareas([geometria.coordinates[0]]) > 500);
});

test('el shapefile en 9377 no se toma como grados', () => {
    assert.equal(sridDeArchivo('predios.zip', 'PROJCS["MAGNA-SIRGAS / CTM12",AUTHORITY["EPSG","9377"]]', {
        type: 'Polygon',
        coordinates: [[[4916000, 1998000], [4916100, 1998000], [4916100, 1998100], [4916000, 1998100], [4916000, 1998000]]],
    }), 9377);
    assert.equal(origenDeNombre('predios.zip'), 'shapefile');
    assert.equal(sridDeArchivo('area.kml', '', { type: 'Point', coordinates: [-73.6, 4.1] }), 4326);
    assert.equal(sridDeArchivo('desconocido.zip', '', {
        type: 'Polygon',
        coordinates: [[[500000, 200000], [500100, 200000], [500100, 200100], [500000, 200100], [500000, 200000]]],
    }), null);
});

test('dibujar un polígono actualiza el panel y deja la geometría lista para procesar', () => {
    const coleccion = {
        type: 'FeatureCollection',
        features: [{
            type: 'Feature',
            geometry: {
                type: 'Polygon',
                coordinates: [[[-73.78, 3.97], [-73.75, 3.97], [-73.75, 4.00], [-73.78, 4.00], [-73.78, 3.97]]],
            },
        }],
    };
    const panel = { textoArea: 'dibuje o cargue un área', puedeProcesar: false, mensaje: null };
    const wire = cable();

    const estado = publicarColeccion(coleccion, panel, wire);

    assert.equal(estado.sincronizado, true);
    assert.equal(panel.puedeProcesar, true);
    assert.match(panel.textoArea, /^\d{1,3}(\.\d{3})*,\d{2} ha$/);
    assert.notEqual(panel.textoArea, 'dibuje o cargue un área');
    assert.match(wire.geojson, /"type":"Polygon"/);
    assert.equal(wire.origen, 'dibujo');
    assert.equal(wire.sridOrigen, 4326);
    assert.equal(wire.committed, true);
    assert.ok(hectareasDe(panel.textoArea) > 500);
});

test('sin polígono el panel no habilita procesar', () => {
    const panel = { textoArea: '12,00 ha', puedeProcesar: true, mensaje: null };
    const wire = cable();
    wire.geojson = 'anterior';

    const estado = publicarColeccion({ type: 'FeatureCollection', features: [] }, panel, wire);

    assert.equal(estado.puedeProcesar, false);
    assert.equal(panel.textoArea, 'dibuje o cargue un área');
    assert.equal(wire.geojson, null);
    assert.equal(wire.committed, true);
});

test('el área se muestra aunque Livewire todavía no esté listo y luego se puede procesar', () => {
    const coleccion = {
        type: 'Polygon',
        coordinates: [[[-73.78, 3.97], [-73.75, 3.97], [-73.75, 4.00], [-73.78, 4.00], [-73.78, 3.97]]],
    };
    const panel = { textoArea: 'dibuje o cargue un área', puedeProcesar: false, mensaje: null };

    const pendiente = publicarColeccion(coleccion, panel, null);

    assert.equal(pendiente.sincronizado, false);
    assert.equal(panel.puedeProcesar, true);
    assert.match(panel.textoArea, /ha$/);

    const wire = cable();
    const sincronizado = aplicarEstado(panel, wire, pendiente);

    assert.equal(sincronizado.sincronizado, true);
    assert.match(wire.geojson, /"type":"Polygon"/);
    assert.equal(wire.committed, true);
});

test('formatea hectáreas como el servidor y calcula un cuadrado proyectado', () => {
    assert.equal(formatearHectareas(1234.5), '1.234,50');
    assert.equal(formatearHectareas(1), '1,00');

    const geometria = geometriaDePoligonos({
        type: 'Polygon',
        coordinates: [[[4916000, 1998000], [4916100, 1998000], [4916100, 1998100], [4916000, 1998100], [4916000, 1998000]]],
    });
    const panel = { textoArea: 'dibuje o cargue un área', puedeProcesar: false, mensaje: null };
    const estado = publicarColeccion(geometria, panel, cable(), { origen: 'shapefile', srid: 9377, nombreArchivo: 'predios.zip' });

    assert.equal(estado.areaHa, '1,00');
    assert.equal(panel.textoArea, '1,00 ha');
    assert.equal(estado.origen, 'shapefile');
});

const limite = poligono([[0, 0], [4, 0], [4, 4], [0, 4]]);

test('un polígono dentro del límite se puede procesar y no pinta área fuera', () => {
    const estado = estadoDeGeometria(poligono([[1, 1], [2, 1], [2, 2], [1, 2]]), { limite });

    assert.equal(estado.puedeProcesar, true);
    assert.equal(estado.mensaje, null);
    assert.equal(estado.geometriaFuera, null);
    assert.equal(estado.pctFuera, 0);
});

test('un polígono completamente fuera del límite bloquea procesar', () => {
    const estado = estadoDeGeometria(poligono([[10, 10], [11, 10], [11, 11], [10, 11]]), { limite });

    assert.equal(estado.puedeProcesar, false);
    assert.equal(estado.mensaje, MENSAJE_TOTALMENTE_FUERA);
    assert.ok(estado.pctFuera >= 99.9);
});

test('un rectángulo que cruza el límite avisa hectáreas y porcentaje fuera', () => {
    const estado = estadoDeGeometria(poligono([[2, -1], [6, -1], [6, 2], [2, 2]]), { limite });

    assert.equal(estado.puedeProcesar, true);
    assert.ok(estado.pctFuera > 60 && estado.pctFuera < 75, `pct ${estado.pctFuera}`);
    assert.match(estado.mensaje, /Quedaron fuera del Meta .+ ha \(.+%\). Esa parte no se analizará porque las capas cubren solo el Meta\./);
    assert.equal(contiene(estado.geometriaFuera, [5, 1]), true);
    assert.equal(contiene(estado.geometriaFuera, [3, 1]), false);
});

test('un recorte contra una L conserva la esquina que queda fuera', () => {
    const ele = poligono([[0, 0], [3, 0], [3, 1], [1, 1], [1, 3], [0, 3]]);
    const estado = estadoDeGeometria(poligono([[0.2, -0.5], [2.5, -0.5], [2.5, 2.5], [0.2, 2.5]]), { limite: ele });

    assert.equal(estado.puedeProcesar, true);
    assert.ok(estado.pctFuera > 10 && estado.pctFuera < 70, `pct ${estado.pctFuera}`);
    assert.equal(contiene(estado.geometriaFuera, [2, 2]), true);
    assert.equal(contiene(estado.geometriaFuera, [0.5, 0.5]), false);
});

test('un shapefile en 9377 no se recorta en el cliente', () => {
    const estado = estadoDeGeometria(poligono([[4916000, 1998000], [4916100, 1998000], [4916100, 1998100], [4916000, 1998100]]), {
        limite,
        srid: 9377,
    });

    assert.equal(estado.puedeProcesar, true);
    assert.equal(estado.geometriaFuera, null);
});

function poligono(anillo) {
    return { type: 'Polygon', coordinates: [[...anillo, anillo[0]]] };
}

function contiene(geometria, punto) {
    if (! geometria) {
        return false;
    }

    const anillos = geometria.type === 'Polygon'
        ? [geometria.coordinates[0]]
        : geometria.coordinates.map((poligono) => poligono[0]);

    return anillos.some((anillo) => {
        let adentro = false;

        for (let i = 0, j = anillo.length - 1; i < anillo.length; j = i, i += 1) {
            const xi = anillo[i][0];
            const yi = anillo[i][1];
            const xj = anillo[j][0];
            const yj = anillo[j][1];

            if ((yi > punto[1]) !== (yj > punto[1]) && punto[0] < (((xj - xi) * (punto[1] - yi)) / (yj - yi)) + xi) {
                adentro = ! adentro;
            }
        }

        return adentro;
    });
}

function cable() {
    return {
        set(clave, valor) {
            this[clave] = valor;
        },
        commit() {
            this.committed = true;
        },
    };
}

function hectareasDe(texto) {
    return Number(texto.replace(' ha', '').replaceAll('.', '').replace(',', '.'));
}
