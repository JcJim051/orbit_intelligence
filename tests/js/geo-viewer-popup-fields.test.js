import assert from 'node:assert/strict';
import test from 'node:test';
import { geoViewerPopupFields } from '../../resources/js/geo-viewer-popup-fields.js';

test('una capa SIID muestra todos los atributos recibidos aunque no tenga campos configurados', () => {
    assert.deepEqual(
        geoViewerPopupFields({ nombre: 'Hospital', municipio: 'Villavicencio', camas: 12 }, [], true),
        ['nombre', 'municipio', 'camas'],
    );
});

test('los campos configurados se muestran primero sin ocultar los demás atributos públicos', () => {
    assert.deepEqual(
        geoViewerPopupFields({ codigo: 7, nombre: 'Río Guatiquía', caudal: null }, ['nombre', 'nombre', 'inexistente'], true),
        ['nombre', 'codigo', 'caudal'],
    );
});

test('las capas externas solo muestran los campos configurados', () => {
    assert.deepEqual(
        geoViewerPopupFields({ nombre: 'Escuela', contacto_privado: '555' }, ['nombre']),
        ['nombre'],
    );
    assert.deepEqual(geoViewerPopupFields({ nombre: 'Escuela' }), []);
});

test('una entidad sin atributos no genera campos de ficha', () => {
    assert.deepEqual(geoViewerPopupFields({}, ['nombre'], true), []);
});
