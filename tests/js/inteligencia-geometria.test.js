import assert from 'node:assert/strict';
import test from 'node:test';
import { areaHectareas, aplicarEstado, formatearHectareas, geometriaDePoligonos, origenDeNombre, publicarColeccion, sridDeArchivo } from '../../resources/js/inteligencia-geometria.js';

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
