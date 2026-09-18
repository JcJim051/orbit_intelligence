import assert from 'node:assert/strict';
import test from 'node:test';
import { assignDistinctLayerColors, validLayerColor } from '../../resources/js/geo-viewer-layer-colors.js';

const geojsonLayer = (slug, fillColor) => ({
    slug,
    source: { type: 'geojson' },
    style: { color: '#4338ca', fillColor },
});

test('capas GeoJSON con el mismo color se dibujan con colores distintos y estables', () => {
    const layers = [
        geojsonLayer('centros', '#818cf8'),
        geojsonLayer('drenajes', '#818cf8'),
        geojsonLayer('municipios', '#818cf8'),
    ];

    const colored = assignDistinctLayerColors(layers);

    assert.deepEqual(colored.map(layer => layer.style.fillColor), ['#818cf8', '#2563eb', '#dc2626']);
    assert.equal(colored[1].style.color, '#2563eb');
    assert.equal(layers[1].style.fillColor, '#818cf8');
    assert.deepEqual(assignDistinctLayerColors(layers), colored);
});

test('un color propio se conserva y una capa WMS no se recolorea en el cliente', () => {
    const wms = { slug: 'fondo', source: { type: 'wms' }, style: { fillColor: '#818cf8' } };
    const layers = [geojsonLayer('centros', '#059669'), wms, geojsonLayer('drenajes', '#818cf8')];

    const colored = assignDistinctLayerColors(layers);

    assert.equal(colored[0].style.fillColor, '#059669');
    assert.equal(colored[1], wms);
    assert.equal(colored[2].style.fillColor, '#818cf8');
});

test('el selector solo admite colores hexadecimales seguros', () => {
    assert.equal(validLayerColor('#AABBCC'), '#aabbcc');
    assert.equal(validLayerColor('red'), null);
    assert.equal(validLayerColor('javascript:alert(1)'), null);
});
