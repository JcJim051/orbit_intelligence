import assert from 'node:assert/strict';
import test from 'node:test';
import { buildChoroplethScale, choroplethLegendEntries } from '../../resources/js/geo-viewer-choropleth.js';

const feature = value => ({ properties: { valor: value } });

test('clasifica valores numéricos en cuantiles y conserva un color para datos ausentes', () => {
    const scale = buildChoroplethScale(
        [feature(10), feature(20), feature(30), feature(40), feature(50), feature(null)],
        { property: 'valor', palette: ['#dcfce7', '#4ade80', '#166534'], noDataColor: '#e2e8f0' },
    );

    assert.deepEqual(scale.thresholds, [20, 40]);
    assert.equal(scale.color(10), '#dcfce7');
    assert.equal(scale.color(30), '#4ade80');
    assert.equal(scale.color(50), '#166534');
    assert.equal(scale.color(null), '#e2e8f0');
    assert.deepEqual(choroplethLegendEntries(scale), [
        { color: '#dcfce7', minimum: null, maximum: 20 },
        { color: '#4ade80', minimum: 20, maximum: 40 },
        { color: '#166534', minimum: 40, maximum: null },
    ]);
});

test('ignora valores no numéricos al calcular los cortes', () => {
    const scale = buildChoroplethScale([feature(''), feature('sin dato'), feature(undefined)]);

    assert.deepEqual(scale.thresholds, []);
    assert.equal(scale.color('sin dato'), '#e2e8f0');
});
