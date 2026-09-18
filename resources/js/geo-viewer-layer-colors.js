const layerPalette = [
    '#2563eb', '#dc2626', '#059669', '#d97706', '#7c3aed', '#0891b2',
    '#be185d', '#4d7c0f', '#c2410c', '#0f766e', '#a21caf', '#4338ca',
];

export function validLayerColor(value) {
    return typeof value === 'string' && /^#[\da-f]{6}$/i.test(value) ? value.toLowerCase() : null;
}

export function assignDistinctLayerColors(layers) {
    const used = new Set();

    return layers.map(layer => {
        if (layer.source.type !== 'geojson') {
            return layer;
        }

        const preferred = validLayerColor(layer.style?.fillColor)
            ?? validLayerColor(layer.style?.color)
            ?? '#818cf8';
        let selected = preferred;

        if (used.has(selected)) {
            selected = layerPalette.find(color => ! used.has(color));
            for (let hue = 0; ! selected; hue += 1) {
                const candidate = hslToHex((hue * 137.508) % 360, 70, 42);
                if (! used.has(candidate)) {
                    selected = candidate;
                }
            }
        }

        used.add(selected);

        return selected === preferred
            ? layer
            : { ...layer, style: { ...layer.style, color: selected, fillColor: selected } };
    });
}

function hslToHex(hue, saturation, lightness) {
    const a = saturation * Math.min(lightness, 100 - lightness) / 10000;
    const channel = offset => {
        const k = (offset + hue / 30) % 12;
        return Math.round(255 * (lightness / 100 - a * Math.max(-1, Math.min(k - 3, 9 - k, 1))))
            .toString(16).padStart(2, '0');
    };

    return `#${channel(0)}${channel(8)}${channel(4)}`;
}
