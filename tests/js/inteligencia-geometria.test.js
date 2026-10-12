import assert from 'node:assert/strict';
import test from 'node:test';
import { areaHectareas, geometriaDePoligonos, origenDeNombre, sridDeArchivo } from '../../resources/js/inteligencia-geometria.js';

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
