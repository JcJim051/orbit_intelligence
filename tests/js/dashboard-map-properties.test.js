import assert from 'node:assert/strict';
import test from 'node:test';
import { territoryLabel } from '../../resources/js/dashboard-map-properties.js';

test('usa el nombre institucional del municipio antes que el código territorial', () => {
    assert.equal(territoryLabel({ mp_codigo: '50001', mp_nombre: 'Villavicencio' }, '50001'), 'Villavicencio');
});

test('reconoce nombres territoriales sin depender de mayúsculas', () => {
    assert.equal(territoryLabel({ MP_NOMBRE: 'Acacías' }, '50006'), 'Acacías');
    assert.equal(territoryLabel({ municipio: 'Granada' }, '50313'), 'Granada');
});

test('usa el código como respaldo cuando no existe un nombre publicado', () => {
    assert.equal(territoryLabel({ mp_codigo: '50001' }, '50001'), '50001');
});
